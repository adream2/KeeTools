<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\App;
use App\Core\Config;
use App\Core\Database;
use App\Core\Logger;
use App\Core\Security;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * 离线合集包打包服务（P3）
 *
 * 职责与安全边界（tasks/P3-离线包.md §一）：
 *  - 异步任务模式：createTask() 只写任务行，run()/processPending() 消费；
 *    CLI（scripts/build_package.php）与后台轮询端点共用同一实现
 *  - 中间产物一律落 var/tmp/package-{taskId}/，最终 zip 落 packages/
 *  - ZipArchive 逐文件 addFile（禁止 addFromString 读大文件进内存），分批 close/reopen
 *  - 体积守卫 PACKAGE_MAX_SIZE_MB（默认 10MB），超限中止并提示拆包
 *  - 并发锁：DB running 状态 + var/tmp/package.lock 文件锁双保险，同时只跑 1 个任务
 *  - 版本一致性：工具 HTML 内 ET-META 版本必须与站点记录一致，避免发旧包
 *  - 工具文件路径必须过 Security::safePath()（路径穿越唯一防线）
 */
final class PackageBuilder
{
    /** 打包锁文件（var/tmp/ 内，进程级互斥） */
    private const LOCK_FILE = 'var/tmp/package.lock';

    /** running 状态任务视为挂死的超时（秒） */
    private const STALE_TASK_SECONDS = 900;

    /** 临时目录残留清理阈值（秒） */
    private const STALE_TMP_SECONDS = 7200;

    /** zip 每写入多少文件 close/reopen 一次（防止底层缓冲无限增长） */
    private const ZIP_BATCH = 100;

    private ?Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db;
    }

    // ───────────────────────────────────────────
    // 任务创建 / 查询
    // ───────────────────────────────────────────

    /**
     * 创建打包任务（只入库，不执行）。
     *
     * @param list<string> $toolIds
     * @return int 任务 id
     */
    public function createTask(
        string $slug,
        string $version,
        array $toolIds,
        bool $withPortal,
        string $createdBy = ''
    ): int {
        $slug = $this->normalizeSlug($slug);
        if (preg_match('/^\d+\.\d+\.\d+$/', $version) !== 1) {
            throw new RuntimeException('包版本必须为语义化版本，如 1.0.0');
        }

        $toolIds = array_values(array_unique(array_map(
            static fn (string $id): string => trim($id),
            $toolIds
        )));
        $toolIds = array_values(array_filter($toolIds, static fn (string $id): bool => $id !== ''));
        if ($toolIds === []) {
            throw new RuntimeException('请至少勾选一个工具');
        }
        foreach ($toolIds as $id) {
            if (!Security::isValidToolId($id)) {
                throw new RuntimeException('非法工具 id: ' . $id);
            }
        }

        // 工具必须已在库中且满足分发条件（单文件 + 离线可用 + 已上架）
        $placeholders = implode(', ', array_map(
            static fn (int $i): string => ':tid' . $i,
            array_keys($toolIds)
        ));
        $params = [];
        foreach ($toolIds as $i => $id) {
            $params[':tid' . $i] = $id;
        }
        $eligible = $this->db()->fetchAll(
            'SELECT tool_id FROM tools
             WHERE tool_id IN (' . $placeholders . ')
               AND is_published = 1 AND single_file = 1 AND offline = 1',
            $params
        );
        $eligibleIds = array_column($eligible, 'tool_id');
        $missing = array_diff($toolIds, $eligibleIds);
        if ($missing !== []) {
            throw new RuntimeException(
                '以下工具不可打包（未上架 / 非单文件 / 不支持离线 / 不存在）：' . implode('、', $missing)
            );
        }

        $now = date('Y-m-d H:i:s');

        return $this->db()->insert(
            'INSERT INTO package_tasks
                (slug, version, tool_ids, with_portal, status, progress, stage, message,
                 package_id, created_by, created_at, updated_at)
             VALUES
                (:slug, :version, :tool_ids, :portal, \'pending\', 0, \'\', \'\', NULL, :by, :now, :now)',
            [
                ':slug'     => $slug,
                ':version'  => $version,
                ':tool_ids' => json_encode($toolIds, JSON_UNESCAPED_UNICODE),
                ':portal'   => $withPortal ? 1 : 0,
                ':by'       => $createdBy,
                ':now'      => $now,
            ]
        );
    }

    public function findTask(int $taskId): ?array
    {
        $row = $this->db()->fetch('SELECT * FROM package_tasks WHERE id = :id', [':id' => $taskId]);
        return $row === null ? null : $this->hydrateTask($row);
    }

    /** 最新一条任务（后台进度区用）。 */
    public function latestTask(): ?array
    {
        $row = $this->db()->fetch('SELECT * FROM package_tasks ORDER BY id DESC LIMIT 1');
        return $row === null ? null : $this->hydrateTask($row);
    }

    /**
     * 处理队列：无 running 任务时取最早一条 pending 执行。
     *
     * 供后台轮询端点惰性消费（无 cron 也能工作）。锁被占用或无任务时返回 null；
     * 打包失败时任务已被标记 failed，此方法吞掉异常并返回任务 id（状态由调用方查询展示）。
     */
    public function processPending(): ?int
    {
        if (!$this->hasPending()) {
            return null;
        }

        $id = $this->db()->fetchColumn(
            'SELECT id FROM package_tasks WHERE status = \'pending\' ORDER BY id ASC LIMIT 1'
        );
        if ($id === null) {
            return null;
        }
        $taskId = (int) $id;

        try {
            $this->run($taskId);
        } catch (Throwable $e) {
            // 「已有任务进行中」= 锁被别的进程持有，静默跳过等下次轮询；
            // 其余失败 run() 内部已落库 failed，这里不外抛以免轮询端点 500
            if (!str_contains($e->getMessage(), '已有打包任务在进行中')) {
                Logger::error('打包任务执行失败', ['task' => $taskId, 'message' => $e->getMessage()]);
            }
        }

        return $taskId;
    }

    private function hasPending(): bool
    {
        return ((int) $this->db()->fetchColumn(
            "SELECT COUNT(*) FROM package_tasks WHERE status IN ('pending', 'running')"
        )) > 0;
    }

    // ───────────────────────────────────────────
    // 打包执行
    // ───────────────────────────────────────────

    /**
     * 执行一个打包任务。
     *
     * @throws RuntimeException 任务不存在 / 状态不允许 / 打包失败
     */
    public function run(int $taskId): void
    {
        $task = $this->findTask($taskId);
        if ($task === null) {
            throw new RuntimeException('打包任务不存在');
        }
        if (in_array($task['status'], ['running', 'done'], true)) {
            throw new RuntimeException('任务已在运行或已完成');
        }

        // 先回收历史挂死任务与残留临时目录，再抢锁
        $this->cleanupStale();

        $lock = $this->acquireLock();
        $tmpDir = App::path('var/tmp/package-' . $taskId);

        try {
            $this->updateTask($taskId, [
                'status'   => 'running',
                'progress' => 2,
                'stage'    => '准备临时目录',
                'message'  => '',
            ]);

            if (is_dir($tmpDir)) {
                $this->rrmdir($tmpDir);
            }
            if (!@mkdir($tmpDir, 0775, true) && !is_dir($tmpDir)) {
                throw new RuntimeException('无法创建临时目录: ' . $tmpDir);
            }

            $slug = (string) $task['slug'];
            $version = (string) $task['version'];
            $toolIds = $task['tool_ids'];
            $rootName = $slug . '-' . $version;
            $rootDir = $tmpDir . DIRECTORY_SEPARATOR . $rootName;
            $toolsDir = $rootDir . DIRECTORY_SEPARATOR . 'tools';
            foreach ([$rootDir, $toolsDir] as $dir) {
                if (!@mkdir($dir, 0775, true) && !is_dir($dir)) {
                    throw new RuntimeException('无法创建目录: ' . $dir);
                }
            }

            // ── 1. 复制工具单文件（含版本一致性校验与体积守卫）──
            $maxBytes = $this->maxSizeBytes();
            $totalBytes = 0;
            $index = [];
            $count = count($toolIds);
            foreach ($toolIds as $i => $toolId) {
                $row = $this->db()->fetch(
                    'SELECT tool_id, title, description, version, type, grade_range, subjects, tags,
                            entry, dir_path
                     FROM tools WHERE tool_id = :id',
                    [':id' => $toolId]
                );
                if ($row === null) {
                    throw new RuntimeException("工具 {$toolId} 不在库中：请确认 tools/ 目录下存在该工具（放入后自动同步）");
                }

                $src = Security::safePath(App::path('tools'), (string) $row['dir_path'], (string) $row['entry']);
                if (!is_file($src)) {
                    throw new RuntimeException("工具 {$toolId} 的 entry 文件缺失，请检查 tools/ 目录");
                }

                $metaVersion = self::readMetaVersion($src);
                if ($metaVersion !== null && $metaVersion !== (string) $row['version']) {
                    throw new RuntimeException(sprintf(
                        '工具 %s 文件内 ET-META 版本（%s）与 manifest（%s）不一致，请先修正后重试打包',
                        $toolId,
                        $metaVersion,
                        (string) $row['version']
                    ));
                }

                $dest = $toolsDir . DIRECTORY_SEPARATOR . $toolId . '.html';
                if (!@copy($src, $dest)) {
                    throw new RuntimeException("工具 {$toolId} 复制失败");
                }

                $size = (int) (filesize($src) ?: 0);
                $totalBytes += $size;
                if ($totalBytes > $maxBytes) {
                    throw new RuntimeException(sprintf(
                        '包体超过 %.0fMB 上限（工具原始体积已 %.2fMB），请拆分为多个包',
                        $maxBytes / 1048576,
                        $totalBytes / 1048576
                    ));
                }

                $index[] = [
                    'id'          => $toolId,
                    'title'       => (string) $row['title'],
                    'description' => (string) $row['description'],
                    'type'        => (string) $row['type'],
                    'version'     => (string) $row['version'],
                    'grade_range' => self::jsonArray((string) $row['grade_range']),
                    'subjects'    => self::jsonArray((string) $row['subjects']),
                    'tags'        => self::jsonArray((string) $row['tags']),
                    'file'        => 'tools/' . $toolId . '.html',
                ];

                $this->updateTask($taskId, [
                    'progress' => 5 + (int) (70 * ($i + 1) / $count),
                    'stage'    => '复制工具（' . ($i + 1) . '/' . $count . '）',
                ]);
            }

            // ── 2. 本地工具索引（门户数据源与数据附赠）──
            $indexJson = json_encode(
                [
                    'name'         => $rootName,
                    'generated_at' => date('c'),
                    'tools'        => $index,
                ],
                JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
            );
            if ($indexJson === false) {
                throw new RuntimeException('tools-index.json 序列化失败');
            }
            file_put_contents($rootDir . DIRECTORY_SEPARATOR . 'tools-index.json', $indexJson);
            $totalBytes += strlen($indexJson);

            // ── 3. 导航门户（与在线版共用同一外壳模板）──
            if ($task['with_portal']) {
                $this->updateTask($taskId, ['progress' => 80, 'stage' => '生成导航门户']);
                $html = (new PortalRenderer())->render($index, $rootName, $slug, $version);
                file_put_contents($rootDir . DIRECTORY_SEPARATOR . 'index.html', $html);
                $totalBytes += strlen($html);
            }

            // ── 4. 说明文件（txt 快速上手 + HTML 打印版，浏览器打印即存 PDF）──
            $this->updateTask($taskId, ['progress' => 84, 'stage' => '写入说明文件']);
            $siteUrl = site_url();
            $qrDataUri = (new PortalRenderer())->qrDataUri();
            $textFiles = [
                '使用说明.txt'       => self::usageText($version, $count, $siteUrl),
                '关于KeeTools.txt'  => self::aboutText($slug, $version, $siteUrl),
            ];
            foreach ($textFiles as $name => $content) {
                // UTF-8 BOM：保证 Win7 记事本等旧编辑器打开不乱码
                file_put_contents($rootDir . DIRECTORY_SEPARATOR . $name, "\xEF\xBB\xBF" . $content);
                $totalBytes += strlen($content) + 3;
            }

            $usageHtml = self::usageHtml($version, $siteUrl, $index, $qrDataUri);
            file_put_contents($rootDir . DIRECTORY_SEPARATOR . '使用说明.html', $usageHtml);
            $totalBytes += strlen($usageHtml);

            if ($totalBytes > $maxBytes) {
                throw new RuntimeException(sprintf(
                    '包体超过 %.0fMB 上限（原始内容 %.2fMB），请拆分为多个包',
                    $maxBytes / 1048576,
                    $totalBytes / 1048576
                ));
            }

            // ── 5. 压缩 ──
            $this->updateTask($taskId, ['progress' => 88, 'stage' => '压缩打包']);
            $zipName = $rootName . '.zip';
            $packagesDir = App::path('packages');
            if (!is_dir($packagesDir) && !@mkdir($packagesDir, 0775, true) && !is_dir($packagesDir)) {
                throw new RuntimeException('无法创建产物目录: ' . $packagesDir);
            }
            $zipPath = $packagesDir . DIRECTORY_SEPARATOR . $zipName;
            if (is_file($zipPath)) {
                @unlink($zipPath);
            }
            $this->zipDir($tmpDir, $zipPath);

            $zipBytes = (int) (filesize($zipPath) ?: 0);
            if ($zipBytes > $maxBytes) {
                @unlink($zipPath);
                throw new RuntimeException(sprintf(
                    'zip 产物 %.2fMB 超过 %.0fMB 上限，已中止，请拆分为多个包',
                    $zipBytes / 1048576,
                    $maxBytes / 1048576
                ));
            }

            // ── 6. 产物台账 + 网盘上传清单（zip 外，站长用，不随包分发）──
            $sha256 = (string) hash_file('sha256', $zipPath);
            $now = date('Y-m-d H:i:s');
            $packageId = $this->db()->insert(
                'INSERT INTO packages
                    (slug, version, tool_ids, tool_count, size_bytes, sha256, file_name, task_id, created_at)
                 VALUES
                    (:slug, :version, :tool_ids, :count, :size, :sha, :name, :task, :now)',
                [
                    ':slug'     => $slug,
                    ':version'  => $version,
                    ':tool_ids' => json_encode($toolIds, JSON_UNESCAPED_UNICODE),
                    ':count'    => $count,
                    ':size'     => $zipBytes,
                    ':sha'      => $sha256,
                    ':name'     => $zipName,
                    ':task'     => $taskId,
                    ':now'      => $now,
                ]
            );

            // 同名旧产物行清理（重新打包覆盖 zip 后旧台账不再指向真实校验和）
            $this->db()->execute(
                'DELETE FROM packages WHERE file_name = :name AND id != :id',
                [':name' => $zipName, ':id' => $packageId]
            );

            @file_put_contents(
                $packagesDir . DIRECTORY_SEPARATOR . $rootName . '-网盘上传清单.txt',
                "\xEF\xBB\xBF" . self::uploadChecklistText($zipName, $zipBytes, $sha256, $siteUrl)
            );

            $this->updateTask($taskId, [
                'status'     => 'done',
                'progress'   => 100,
                'stage'      => '完成',
                'package_id' => $packageId,
                'message'    => sprintf(
                    '已生成 %s（%d 个工具，%.2fMB）',
                    $zipName,
                    $count,
                    $zipBytes / 1048576
                ),
            ]);
        } catch (Throwable $e) {
            $this->updateTask($taskId, [
                'status'  => 'failed',
                'stage'   => '失败',
                'message' => $e->getMessage(),
            ]);
            $this->rrmdir($tmpDir);
            $this->releaseLock($lock);
            throw $e;
        }

        $this->rrmdir($tmpDir);
        $this->releaseLock($lock);
    }

    // ───────────────────────────────────────────
    // 维护
    // ───────────────────────────────────────────

    /**
     * 回收挂死任务（running 超 15 分钟）与残留临时目录（超 2 小时）。
     * 任务成功 / 失败 / 超时后临时目录均会被清理。
     */
    public function cleanupStale(): void
    {
        $this->db()->execute(
            "UPDATE package_tasks
             SET status = 'failed', stage = '失败', message = '任务超时被回收', updated_at = :now
             WHERE status = 'running' AND updated_at < :cutoff",
            [
                ':now'    => date('Y-m-d H:i:s'),
                ':cutoff' => date('Y-m-d H:i:s', time() - self::STALE_TASK_SECONDS),
            ]
        );

        $stale = time() - self::STALE_TMP_SECONDS;
        foreach (glob(App::path('var/tmp/package-*'), GLOB_ONLYDIR) ?: [] as $dir) {
            $mtime = @filemtime($dir);
            if ($mtime !== false && $mtime < $stale) {
                $this->rrmdir($dir);
            }
        }
    }

    /**
     * 删除产物：zip + 同名网盘上传清单 + 台账行。返回是否删除了磁盘文件。
     */
    public function deletePackage(int $packageId): bool
    {
        $row = $this->db()->fetch('SELECT id, file_name FROM packages WHERE id = :id', [':id' => $packageId]);
        if ($row === null) {
            throw new RuntimeException('产物不存在');
        }

        $fileName = (string) $row['file_name'];
        $file = App::path('packages') . DIRECTORY_SEPARATOR . $fileName;
        $removed = is_file($file) && @unlink($file);

        // 网盘上传清单（{slug}-{version}-网盘上传清单.txt）一并清理
        $checklist = App::path('packages') . DIRECTORY_SEPARATOR
            . basename($fileName, '.zip') . '-网盘上传清单.txt';
        if (is_file($checklist)) {
            @unlink($checklist);
        }

        $this->db()->execute('DELETE FROM packages WHERE id = :id', [':id' => $packageId]);

        return $removed;
    }

    /** 产物文件绝对路径（下载用）。 */
    public function packagePath(string $fileName): string
    {
        // file_name 由系统生成（{slug}-{version}.zip），basename 再兜底一层
        return App::path('packages') . DIRECTORY_SEPARATOR . basename($fileName);
    }

    // ───────────────────────────────────────────
    // 内部
    // ───────────────────────────────────────────

    private function maxSizeBytes(): int
    {
        return Config::int('PACKAGE_MAX_SIZE_MB', 10) * 1024 * 1024;
    }

    private function normalizeSlug(string $slug): string
    {
        $slug = strtolower(trim($slug));
        $slug = (string) preg_replace('/[^a-z0-9-]+/', '-', $slug);
        $slug = (string) preg_replace('/-+/', '-', $slug);
        $slug = trim($slug, '-');
        if ($slug === '' || strlen($slug) > 64) {
            throw new RuntimeException('包标识须为 1~64 位小写字母 / 数字 / 连字符');
        }

        return $slug;
    }

    /**
     * 读工具 HTML 头部的 ET-META 版本。没有该标记返回 null（不阻断打包）。
     */
    private static function readMetaVersion(string $file): ?string
    {
        $head = (string) file_get_contents($file, false, null, 0, 8192);
        if (preg_match('/<!--\s*ET-META\s*(\{.*?\})\s*-->/', $head, $m) !== 1) {
            return null;
        }
        $meta = json_decode($m[1], true);
        if (!is_array($meta) || !isset($meta['version']) || !is_scalar($meta['version'])) {
            return null;
        }

        return (string) $meta['version'];
    }

    /** @return list<mixed> */
    private static function jsonArray(string $json): array
    {
        $decoded = json_decode($json, true);

        return is_array($decoded) ? array_values($decoded) : [];
    }

    /**
     * 获取进程级打包锁。拿到锁返回文件句柄（调用方负责 releaseLock）。
     *
     * @throws RuntimeException 锁被占用
     */
    private function acquireLock()
    {
        $path = App::path(self::LOCK_FILE);
        $dir = dirname($path);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('无法创建锁目录: ' . $dir);
        }

        $handle = @fopen($path, 'c');
        if ($handle === false || !flock($handle, LOCK_EX | LOCK_NB)) {
            if (is_resource($handle)) {
                fclose($handle);
            }
            throw new RuntimeException('已有打包任务在进行中，请稍后再试');
        }

        return $handle;
    }

    private function releaseLock($handle): void
    {
        if (is_resource($handle)) {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    /**
     * 递归删除目录（临时目录清理）。目录不存在时静默返回。
     */
    private function rrmdir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            if ($item->isDir() && !$item->isLink()) {
                @rmdir($item->getPathname());
            } else {
                @unlink($item->getPathname());
            }
        }
        @rmdir($dir);
    }

    /**
     * 递归压缩目录。逐文件 addFile，分批 close/reopen 控制底层缓冲；
     * 排除规则：.DS_Store / node_modules / *.map / *.db（防御性，正常产物不含这些）。
     */
    private function zipDir(string $sourceDir, string $zipPath): void
    {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('服务器 PHP 未启用 zip 扩展（ZipArchive），无法打包。请联系主机商启用 ext-zip。');
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($sourceDir, FilesystemIterator::SKIP_DOTS)
        );
        $files = [];
        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }
            $pathname = $file->getPathname();
            $name = $file->getFilename();
            if ($name === '.DS_Store' || str_ends_with($name, '.map') || str_ends_with($name, '.db')) {
                continue;
            }
            if (str_contains($pathname, DIRECTORY_SEPARATOR . 'node_modules' . DIRECTORY_SEPARATOR)) {
                continue;
            }
            $relative = ltrim(str_replace($sourceDir, '', $pathname), DIRECTORY_SEPARATOR . '/');
            $files[] = [$pathname, str_replace(DIRECTORY_SEPARATOR, '/', $relative)];
        }

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('无法创建 zip 文件: ' . basename($zipPath));
        }

        $count = 0;
        foreach ($files as [$pathname, $relative]) {
            if (!$zip->addFile($pathname, $relative)) {
                $zip->close();
                throw new RuntimeException('zip 写入失败: ' . $relative);
            }
            if (++$count % self::ZIP_BATCH === 0) {
                // 分批落盘：close 冲刷缓冲后以追加模式重新打开
                if (!$zip->close() || $zip->open($zipPath) !== true) {
                    throw new RuntimeException('zip 分批写入失败');
                }
            }
        }

        if (!$zip->close()) {
            throw new RuntimeException('zip 收尾失败，产物不完整');
        }
    }

    /**
     * @param array<string, mixed> $fields
     */
    private function updateTask(int $taskId, array $fields): void
    {
        $fields['updated_at'] = date('Y-m-d H:i:s');
        $sets = [];
        $params = [':id' => $taskId];
        foreach ($fields as $key => $value) {
            $sets[] = $key . ' = :' . $key;
            $params[':' . $key] = $value;
        }

        $this->db()->execute(
            'UPDATE package_tasks SET ' . implode(', ', $sets) . ' WHERE id = :id',
            $params
        );
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function hydrateTask(array $row): array
    {
        $row['id'] = (int) $row['id'];
        $row['with_portal'] = (int) $row['with_portal'] === 1;
        $row['progress'] = (int) $row['progress'];
        $row['package_id'] = $row['package_id'] !== null ? (int) $row['package_id'] : null;
        $decoded = json_decode((string) $row['tool_ids'], true);
        $row['tool_ids'] = is_array($decoded) ? array_values($decoded) : [];

        return $row;
    }

    private function db(): Database
    {
        if ($this->db !== null) {
            return $this->db;
        }
        if (!App::hasDb()) {
            throw new RuntimeException('数据库未初始化');
        }

        return App::db();
    }

    // ───────────────────────────────────────────
    // 纯文本产物
    // ───────────────────────────────────────────

    private static function usageText(string $version, int $count, string $siteUrl): string
    {
        $update = $siteUrl !== '' ? $siteUrl . '/?from=offline-pkg' : '（站点地址未配置）';

        return <<<TXT
课工具 KeeTools 离线合集 v{$version}
====================================

使用方法
1. 打开本目录中的 index.html，即可查看全部 {$count} 个工具的导航，
   支持搜索与按学段 / 学科筛选。
2. tools/ 目录内是各工具的单文件，双击任意文件即用，无需安装。
3. 建议使用 Chrome、Edge 等现代浏览器打开。
4. 所有工具数据保存在您自己电脑的浏览器中，不会上传到任何服务器。

常见问题
- 双击后空白？请更换 Chrome / Edge 浏览器再试。
- 学校电脑限制运行本地文件？可将整个文件夹复制到桌面后打开。

检查更新
{$update}

TXT;
    }

    /**
     * 打印友好版使用说明（HTML，零依赖单文件）。
     *
     * 不直接产 PDF 的原因：嵌入中文字体需引入 TTF 子集化依赖，违背零依赖约束；
     * 改为「浏览器打开 → Ctrl+P → 另存为 PDF」一步得到 PDF，教师无感知差异。
     */
    private static function usageHtml(
        string $version,
        string $siteUrl,
        array $index,
        ?string $qrDataUri
    ): string {
        $siteName = site_name();
        $update = $siteUrl !== '' ? $siteUrl . '/?from=offline-pkg' : '';

        $rows = '';
        foreach ($index as $tool) {
            $grades = grade_range_label(is_array($tool['grade_range']) ? $tool['grade_range'] : []);
            $subjects = is_array($tool['subjects']) ? implode('、', $tool['subjects']) : '';
            $rows .= '<tr><td>' . Security::escape((string) $tool['title'])
                . '</td><td class="mono">' . Security::escape((string) $tool['version'])
                . '</td><td>' . Security::escape($grades)
                . '</td><td>' . Security::escape($subjects)
                . '</td><td class="mono">' . Security::escape((string) $tool['file'])
                . '</td></tr>';
        }

        $qrHtml = $qrDataUri !== null
            ? '<div class="qr"><img src="' . Security::escape($qrDataUri) . '" alt="站点二维码"><span>扫码关注，获取工具更新</span></div>'
            : '';

        $updateHtml = $update !== ''
            ? '<a href="' . Security::escape($update) . '">' . Security::escape($update) . '</a>'
            : '<span class="muted">（部署站点后重新打包生成）</span>';
        $today = date('Y-m-d');
        $toolCount = count($index);

        return <<<HTML
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>使用说明 — 课工具离线合集 v{$version}</title>
<meta name="robots" content="noindex">
<style>
    body { font-family: system-ui, "Microsoft YaHei", sans-serif; max-width: 46rem;
           margin: 2rem auto; padding: 0 1.25rem; color: #1f2937; line-height: 1.7; }
    h1 { font-size: 1.5rem; border-bottom: 2px solid #2563eb; padding-bottom: .5rem; }
    h2 { font-size: 1.15rem; margin-top: 2rem; }
    table { width: 100%; border-collapse: collapse; margin-top: .75rem; font-size: .85rem; }
    th, td { border: 1px solid #d1d5db; padding: .4rem .6rem; text-align: left; }
    th { background: #f3f4f6; }
    .mono { font-family: Consolas, monospace; font-size: .8rem; }
    .muted { color: #6b7280; }
    ol li, ul li { margin: .3rem 0; }
    .qr { display: flex; align-items: center; gap: .75rem; margin-top: 1rem; }
    .qr img { width: 110px; height: 110px; border: 1px solid #d1d5db; border-radius: 6px; padding: 4px; }
    .print-tip { background: #f0f6ff; border: 1px solid #bcd3f8; border-radius: 8px;
                 padding: .75rem 1rem; font-size: .88rem; }
    @media print { .print-tip, .no-print { display: none; } body { margin: 0; } }
</style>
</head>
<body>
<h1>课工具 KeeTools 离线合集 v{$version}</h1>
<p>面向中小学课堂的免费工具集 —— 老师讲课、学生自学、家长辅导都用得上；单文件即开即用，无需安装，断网也能用。</p>

<h2>快速上手</h2>
<ol>
    <li>打开本目录的 <strong>index.html</strong>：全部工具导航，支持搜索与按学段 / 学科筛选。</li>
    <li>tools/ 目录内是各工具的单文件，双击任意文件即用。</li>
    <li>建议使用 Chrome、Edge 等现代浏览器；所有数据只保存在本机浏览器，不上传。</li>
</ol>

<div class="print-tip no-print">需要 PDF 版？直接按 <strong>Ctrl + P</strong>（Mac 为 ⌘ + P）选择「另存为 PDF」即可打印或保存本页。</div>

<h2>工具清单（{$toolCount} 个）</h2>
<table>
    <thead><tr><th>工具</th><th>版本</th><th>适用学段</th><th>学科</th><th>文件</th></tr></thead>
    <tbody>{$rows}</tbody>
</table>

<h2>常见问题</h2>
<ul>
    <li><strong>双击后空白？</strong>请更换 Chrome / Edge 浏览器再试。</li>
    <li><strong>学校电脑限制本地文件？</strong>可将整个文件夹复制到桌面后打开。</li>
    <li><strong>想要新工具？</strong>到在线站点获取最新版本：{$updateHtml}</li>
</ul>

{$qrHtml}

<p class="muted">© {$siteName} · 打包于 {$today}</p>
</body>
</html>
HTML;
    }

    private static function aboutText(string $slug, string $version, string $siteUrl): string
    {
        $siteName = site_name();
        $desc = site_description();
        $update = $siteUrl !== '' ? $siteUrl . '/?from=offline-pkg' : '（站点地址未配置）';
        $generatedAt = date('Y-m-d');

        return <<<TXT
关于 课工具 KeeTools
====================

{$siteName}
{$desc}

课工具是面向中小学课堂的免费工具集：老师、学生、家长都用得上；
单文件即开即用、无需安装、断网也能用。所有工具永久免费，数据全部保存在本地。

在线获取最新工具：{$update}
本合集：{$slug} v{$version}（生成于 {$generatedAt}）
TXT;
    }

    private static function uploadChecklistText(
        string $zipName,
        int $bytes,
        string $sha256,
        string $siteUrl
    ): string {
        $update = $siteUrl !== '' ? $siteUrl . '/?from=offline-pkg' : '（站点地址未配置）';
        $generatedAt = date('Y-m-d H:i:s');
        $mb = sprintf('%.2f', $bytes / 1048576);

        return <<<TXT
网盘上传清单（本文件仅供站长使用，不会进入压缩包）
==================================================
生成时间：{$generatedAt}
包名：{$zipName}
体积：{$bytes} 字节（约 {$mb} MB）
SHA256：{$sha256}

建议网盘：123云盘 / 夸克网盘（限速宽松），百度网盘备用
提取码：____________（自行设置；提取码只填在后台「网盘管理」，
          请勿写入压缩包内——提取码是中间页的转化道具）

上传完成后：
1. 到 后台 → 网盘管理，为对应工具回填网盘链接与提取码
   （详情页的「下载合集包」按钮读取该数据）。
2. 每个工具建议至少配置 2 个网盘互为备份。

检查更新入口：{$update}
TXT;
    }
}
