<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\App;
use App\Core\Database;
use App\Core\Security;
use RuntimeException;

/**
 * 工具扫描器
 *
 * 遍历 tools/ 目录（跳过 _ 前缀目录）→ 校验 manifest → 入库 upsert。
 * 校验规则与 scripts/check_manifest.py 保持一致，但有两处刻意差异：
 *
 *   1. ET-META 缺失 / id / version 不一致 → 不阻断入库，而是置
 *      tools.meta_mismatch = 1 并记入 warnings，让后台列表能「高亮报错」
 *      （P0 验收标准 #5）。提交门禁仍由 check_manifest.py 按 ERR 拦截。
 *   2. CHANGELOG.md 缺失 → warning 而非 error（不影响站点运行，
 *      提交门禁会拦住；扫描器若也硬拦会让工具从后台消失，反而不易修复）。
 *
 * 用户态字段（is_featured / is_published / sort_order）在 upsert 时原样保留；
 * tools/ 不可写期间后台产生的降级覆盖（tool_overrides）会合并进 manifest
 * 后再校验、入库，保证库里数据 = 用户最后一次编辑的结果。
 */
final class ToolScanner
{
    /** 学段取值 → 一级分类 slug；'*' 表示全部学段 */
    private const GRADE_STAGES = [
        '1-2'   => 'primary',
        '3-4'   => 'primary',
        '5-6'   => 'primary',
        '1-6'   => 'primary',
        '7-9'   => 'junior',
        '10-12' => 'senior',
        '1-12'  => '*',
    ];

    private const ALL_STAGES = ['primary', 'junior', 'senior'];

    /** 学科名 → 二级分类 slug 段；'通用' 不挂学科级分类 */
    private const SUBJECT_SLUGS = [
        '语文'     => 'chinese',
        '数学'     => 'math',
        '英语'     => 'english',
        '科学'     => 'science',
        '信息技术' => 'it',
        '体育'     => 'pe',
        '音乐'     => 'music',
        '美术'     => 'art',
        '物理'     => 'physics',
        '化学'     => 'chemistry',
        '生物'     => 'biology',
        '政治'     => 'politics',
        '历史'     => 'history',
        '地理'     => 'geography',
    ];

    private const VALID_TYPES = ['fixed', 'shell', 'experiment'];

    private const VALID_SCREENS = ['large', 'any'];

    private const VALID_GRADES = ['1-2', '3-4', '5-6', '1-6', '7-9', '10-12', '1-12'];

    private const VALID_SUBJECTS = [
        '通用', '语文', '数学', '英语', '物理', '化学', '生物',
        '政治', '历史', '地理', '科学', '信息技术', '体育', '音乐', '美术',
    ];

    private string $toolsPath;

    private ManifestWriter $writer;

    private ?Database $db;

    public function __construct(?string $toolsPath = null, ?ManifestWriter $writer = null, ?Database $db = null)
    {
        $this->toolsPath = $toolsPath ?? App::path('tools');
        $this->writer = $writer ?? new ManifestWriter($toolsPath, $db);
        $this->db = $db;
    }

    /**
     * 扫描全部工具目录并同步入库。
     *
     * @return array{
     *     scanned: int,
     *     created: list<string>,
     *     updated: list<string>,
     *     failed: array<string, list<string>>,
     *     warnings: array<string, list<string>>,
     *     meta_mismatch: list<string>,
     *     missing: list<string>,
     * }
     */
    public function scan(): array
    {
        $report = [
            'scanned'       => 0,
            'created'       => [],
            'updated'       => [],
            'failed'        => [],
            'warnings'      => [],
            'meta_mismatch' => [],
            'missing'       => [],
        ];

        if (!is_dir($this->toolsPath)) {
            $report['failed']['_tools'] = ['tools/ 目录不存在: ' . $this->toolsPath];

            return $report;
        }

        $seen = [];
        $names = scandir($this->toolsPath);
        if ($names === false) {
            $report['failed']['_tools'] = ['tools/ 目录读取失败'];

            return $report;
        }

        foreach ($names as $name) {
            if ($name === '.' || $name === '..' || str_starts_with($name, '_')) {
                continue;
            }
            if (!is_dir($this->toolsPath . DIRECTORY_SEPARATOR . $name)) {
                continue;
            }

            $report['scanned']++;
            $seen[$name] = true;

            $result = $this->scanOne($name);
            $toolId = $result['tool_id'] ?? $name;

            if ($result['status'] === 'skipped') {
                $report['failed'][$toolId] = $result['errors'];
                if ($result['warnings'] !== []) {
                    $report['warnings'][$toolId] = $result['warnings'];
                }
                continue;
            }

            $report[$result['status']][] = $toolId;
            if ($result['warnings'] !== []) {
                $report['warnings'][$toolId] = $result['warnings'];
            }
            if ($result['meta_mismatch']) {
                $report['meta_mismatch'][] = $toolId;
            }
        }

        // 库里有、磁盘上已消失的目录：只报告，不改用户态（是否下架由后台决定）
        if ($this->hasDb()) {
            foreach ($this->db()->fetchAll('SELECT tool_id, dir_path FROM tools') as $row) {
                $dir = (string) $row['dir_path'];
                if ($dir !== '' && !isset($seen[$dir])) {
                    $report['missing'][] = (string) $row['tool_id'];
                }
            }
        }

        return $report;
    }

    /**
     * 扫描单个工具目录。
     *
     * @return array{
     *     tool_id: string|null,
     *     status: string,          // created | updated | skipped
     *     errors: list<string>,
     *     warnings: list<string>,
     *     meta_mismatch: bool,
     * }
     */
    public function scanOne(string $dirName): array
    {
        $result = [
            'tool_id'       => null,
            'status'        => 'skipped',
            'errors'        => [],
            'warnings'      => [],
            'meta_mismatch' => false,
        ];

        // 目录名即工具 id，先过格式关（同时天然排除路径注入形态）
        if (!Security::isValidToolId($dirName)) {
            $result['errors'][] = '目录名不符合工具 id 规范（^[a-z0-9-]+$）';

            return $result;
        }

        $dir = $this->toolsPath . DIRECTORY_SEPARATOR . $dirName;
        $manifestFile = $dir . DIRECTORY_SEPARATOR . 'manifest.json';

        if (!is_file($manifestFile)) {
            $result['errors'][] = '缺少 manifest.json';

            return $result;
        }

        $raw = @file_get_contents($manifestFile);
        if ($raw === false) {
            $result['errors'][] = 'manifest.json 读取失败';

            return $result;
        }

        try {
            $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            $result['errors'][] = 'JSON 解析失败: ' . $e->getMessage();

            return $result;
        }

        if (!is_array($data)) {
            $result['errors'][] = 'manifest 顶层必须是对象';

            return $result;
        }

        // 合并 tools/ 不可写期间后台产生的降级覆盖
        $overrides = $this->writer->overridesFor($dirName);
        if ($overrides !== []) {
            $data = $this->writer->applyOverrides($data, $overrides);
        }

        [$errors, $warnings] = $this->validate($data, $dirName, $dir);

        // ET-META 一致性：只警告 + 打标记，不阻断入库（便于后台可见、可修复）
        [$metaIssues, $mismatch] = $this->checkEtMeta($data, $dir);
        $warnings = array_merge($warnings, $metaIssues);
        $result['meta_mismatch'] = $mismatch;

        if ($errors !== []) {
            $result['errors'] = $errors;
            $result['warnings'] = $warnings;
            if (is_string($data['id'] ?? null)) {
                $result['tool_id'] = $data['id'];
            }

            return $result;
        }

        /** @var array<string, mixed> $data 此处必填字段已全部校验通过 */
        $toolId = (string) $data['id'];
        $result['tool_id'] = $toolId;
        $result['warnings'] = $warnings;

        $mtime = filemtime($manifestFile);
        $result['status'] = $this->upsert($data, $dirName, $mtime === false ? 0 : $mtime, $mismatch);

        return $result;
    }

    /**
     * manifest 校验（与 check_manifest.py 规则一致）。
     *
     * @param array<string, mixed> $data
     * @return array{0: list<string>, 1: list<string>} [errors, warnings]
     */
    private function validate(array $data, string $dirName, string $dir): array
    {
        $errors = [];
        $warnings = [];

        // ── 必填字段 ─────────────────────────────
        foreach (['id', 'title', 'version', 'type', 'description', 'author', 'entry'] as $key) {
            $value = $data[$key] ?? null;
            if (!is_string($value) || trim($value) === '') {
                $errors[] = "{$key} 缺失或非字符串";
            }
        }
        foreach (['single_file', 'offline'] as $key) {
            if (!is_bool($data[$key] ?? null)) {
                $errors[] = "{$key} 缺失或非布尔值";
            }
        }
        foreach (['grade_range', 'subjects', 'tags'] as $key) {
            $value = $data[$key] ?? null;
            if (!is_array($value)) {
                $errors[] = "{$key} 缺失或非数组";
                continue;
            }
            if ($value === []) {
                $errors[] = "{$key} 不能用空数组";
                continue;
            }
            foreach ($value as $item) {
                if (!is_string($item) || trim($item) === '') {
                    $errors[] = "{$key} 含非字符串或空元素";
                    break;
                }
            }
        }
        if (!is_array($data['dependencies'] ?? null)) {
            $errors[] = 'dependencies 缺失或非数组';
        }

        // ── id / version ─────────────────────────
        $id = $data['id'] ?? null;
        if (is_string($id)) {
            if (preg_match('/^[a-z0-9-]+$/', $id) !== 1) {
                $errors[] = "id 格式非法：{$id}";
            }
            if ($id !== $dirName) {
                $errors[] = "id 与目录名不一致 (id: {$id}, dir: {$dirName})";
            }
        }

        $version = $data['version'] ?? null;
        if (is_string($version) && preg_match('/^\d+\.\d+\.\d+$/', $version) !== 1) {
            $errors[] = "version 非语义化版本：{$version}";
        }

        // ── 长度建议 ─────────────────────────────
        $title = $data['title'] ?? null;
        if (is_string($title) && mb_strlen($title) > 40) {
            $warnings[] = 'title 超过 40 字符（' . mb_strlen($title) . '）';
        }
        $description = $data['description'] ?? null;
        if (is_string($description) && mb_strlen($description) > 60) {
            $warnings[] = 'description 超过 60 字符（' . mb_strlen($description) . '）';
        }

        // ── 枚举取值 ─────────────────────────────
        $type = $data['type'] ?? null;
        if (is_string($type) && !in_array($type, self::VALID_TYPES, true)) {
            $errors[] = 'type 取值非法：' . $type;
        }

        $screen = $data['screen'] ?? null;
        if ($screen !== null && !in_array($screen, self::VALID_SCREENS, true)) {
            $errors[] = 'screen 取值非法：' . (is_string($screen) ? $screen : json_encode($screen));
        }

        $family = $data['family'] ?? null;
        if ($family !== null && (!is_string($family) || preg_match('/^[a-z0-9-]+$/', $family) !== 1)) {
            $errors[] = 'family 格式非法';
        }

        // ── entry ────────────────────────────────
        $entry = $data['entry'] ?? null;
        if (is_string($entry)) {
            if ($entry !== 'index.html') {
                $errors[] = "entry 必须为 index.html（当前 {$entry}）";
            } elseif (!is_file($dir . DIRECTORY_SEPARATOR . $entry)) {
                $errors[] = "entry 文件不存在：{$entry}";
            }
        }

        // ── 单文件约束 ───────────────────────────
        if (($data['single_file'] ?? null) === true) {
            $deps = $data['dependencies'] ?? null;
            if (is_array($deps) && $deps !== []) {
                $errors[] = 'single_file=true 但 dependencies 非空（应改 false 并登记例外清单）';
            }
        }

        // ── grade_range / subjects 取值 ──────────
        foreach (($data['grade_range'] ?? []) as $grade) {
            if (is_string($grade) && !in_array($grade, self::VALID_GRADES, true)) {
                $errors[] = "grade_range 取值非法：{$grade}";
            }
        }
        foreach (($data['subjects'] ?? []) as $subject) {
            if (is_string($subject) && !in_array($subject, self::VALID_SUBJECTS, true)) {
                $errors[] = "subjects 取值非法：{$subject}";
            }
        }

        // ── 日期 ─────────────────────────────────
        $createdAt = $data['created_at'] ?? null;
        $updatedAt = $data['updated_at'] ?? null;
        $createdOk = is_string($createdAt) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $createdAt) === 1;
        $updatedOk = is_string($updatedAt) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $updatedAt) === 1;
        if (!$createdOk) {
            $errors[] = 'created_at 缺失或格式非 YYYY-MM-DD';
        }
        if (!$updatedOk) {
            $errors[] = 'updated_at 缺失或格式非 YYYY-MM-DD';
        }
        if ($createdOk && $updatedOk && strcmp($updatedAt, $createdAt) < 0) {
            $errors[] = "updated_at 早于 created_at ({$updatedAt} < {$createdAt})";
        }

        // ── CHANGELOG ────────────────────────────
        if (!is_file($dir . DIRECTORY_SEPARATOR . 'CHANGELOG.md')) {
            $warnings[] = '缺少 CHANGELOG.md（提交前须补齐，check_manifest.py 会按错误拦截）';
        }

        return [$errors, $warnings];
    }

    /**
     * 检查 index.html 内的 ET-META 块与 manifest 的一致性。
     *
     * @param array<string, mixed> $data
     * @return array{0: list<string>, 1: bool} [issues, 是否不一致]
     */
    private function checkEtMeta(array $data, string $dir): array
    {
        $entry = $data['entry'] ?? 'index.html';
        if (!is_string($entry) || $entry !== 'index.html') {
            // entry 本身的错误已在 validate() 报告，这里不再重复
            return [[], false];
        }

        $html = @file_get_contents($dir . DIRECTORY_SEPARATOR . $entry);
        if ($html === false) {
            return [['entry 文件读取失败，无法比对 ET-META'], true];
        }

        if (preg_match('/<!--\s*ET-META\s*(\{.*?\})\s*-->/s', $html, $m) !== 1) {
            return [['index.html 缺少 ET-META 块'], true];
        }

        try {
            $meta = json_decode($m[1], true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [['ET-META JSON 解析失败'], true];
        }

        if (!is_array($meta)) {
            return [['ET-META 必须是 JSON 对象'], true];
        }

        $issues = [];
        $manifestId = is_string($data['id'] ?? null) ? $data['id'] : null;
        $manifestVersion = is_string($data['version'] ?? null) ? $data['version'] : null;

        if (($meta['id'] ?? null) !== $manifestId) {
            $issues[] = 'ET-META id 不一致 (manifest: ' . ($manifestId ?? '?')
                . ', html: ' . (is_string($meta['id'] ?? null) ? $meta['id'] : '?') . ')';
        }
        if (($meta['version'] ?? null) !== $manifestVersion) {
            $issues[] = 'ET-META version 不一致 (manifest: ' . ($manifestVersion ?? '?')
                . ', html: ' . (is_string($meta['version'] ?? null) ? $meta['version'] : '?') . ')';
        }

        return [$issues, $issues !== []];
    }

    /**
     * 入库 upsert：manifest 镜像字段全量刷新，用户态字段原样保留。
     *
     * @param array<string, mixed> $m 已通过校验的 manifest 数据
     * @return string created|updated
     */
    private function upsert(array $m, string $dirName, int $mtime, bool $mismatch): string
    {
        $toolId = (string) $m['id'];

        $cols = [
            'title'         => (string) $m['title'],
            'description'   => (string) $m['description'],
            'version'       => (string) $m['version'],
            'type'          => (string) $m['type'],
            'family'        => isset($m['family']) && is_string($m['family']) ? $m['family'] : null,
            'grade_range'   => $this->jsonEncode($m['grade_range']),
            'subjects'      => $this->jsonEncode($m['subjects']),
            'tags'          => $this->jsonEncode($m['tags']),
            'author'        => (string) $m['author'],
            'entry'         => (string) $m['entry'],
            'single_file'   => $m['single_file'] === true ? 1 : 0,
            'offline'       => $m['offline'] === true ? 1 : 0,
            'screen'        => is_string($m['screen'] ?? null) ? $m['screen'] : 'large',
            'stats_enabled' => array_key_exists('stats_enabled', $m) ? ($m['stats_enabled'] === true ? 1 : 0) : 1,
            'license'       => is_string($m['license'] ?? null) ? $m['license'] : 'free',
            'dir_path'      => $dirName,
            'manifest_mtime' => $mtime,
            'meta_mismatch' => $mismatch ? 1 : 0,
            'updated_at'    => (string) $m['updated_at'],
        ];

        $db = $this->db();
        $exists = $db->fetch(
            'SELECT tool_id FROM tools WHERE tool_id = :id',
            [':id' => $toolId]
        ) !== null;

        if ($exists) {
            $sets = implode(', ', array_map(
                static fn (string $key): string => "{$key} = :{$key}",
                array_keys($cols)
            ));
            $cols['where_tool_id'] = $toolId;
            $db->execute("UPDATE tools SET {$sets} WHERE tool_id = :where_tool_id", $cols);
        } else {
            $cols['tool_id'] = $toolId;
            $cols['is_published'] = 1;
            $cols['created_at'] = (string) $m['created_at'];
            $keys = array_keys($cols);
            $placeholders = implode(', ', array_map(
                static fn (string $key): string => ':' . $key,
                $keys
            ));
            $db->execute(
                'INSERT INTO tools (' . implode(', ', $keys) . ') VALUES (' . $placeholders . ')',
                $cols
            );
        }

        $this->syncCategories($toolId, $m);

        return $exists ? 'updated' : 'created';
    }

    /**
     * 按 grade_range + subjects 重算工具与两级分类的关联（全量重建）。
     *
     * 学段映射见 manifest 规范 §3.1：1-2/3-4/5-6/1-6 → 小学，7-9 → 初中，
     * 10-12 → 高中，1-12 → 全部；'通用' 学科只挂学段一级分类。
     *
     * @param array<string, mixed> $m
     */
    private function syncCategories(string $toolId, array $m): int
    {
        $catMap = [];
        foreach ($this->db()->fetchAll('SELECT id, slug, level FROM categories') as $row) {
            $catMap[(string) $row['slug']] = ['id' => (int) $row['id'], 'level' => (int) $row['level']];
        }

        $stages = [];
        foreach (($m['grade_range'] ?? []) as $grade) {
            if (!is_string($grade)) {
                continue;
            }
            $mapped = self::GRADE_STAGES[$grade] ?? null;
            if ($mapped === '*') {
                foreach (self::ALL_STAGES as $stage) {
                    $stages[$stage] = true;
                }
            } elseif ($mapped !== null) {
                $stages[$mapped] = true;
            }
        }

        $categoryIds = [];
        foreach (array_keys($stages) as $stage) {
            $id = $catMap[$stage]['id'] ?? null;
            if ($id !== null) {
                $categoryIds[$id] = true;
            }
        }

        foreach (($m['subjects'] ?? []) as $subject) {
            if (!is_string($subject)) {
                continue;
            }
            $subjectSlug = self::SUBJECT_SLUGS[$subject] ?? null;
            if ($subjectSlug === null) {
                continue; // '通用' 或未登记学科：只挂学段一级
            }
            foreach (array_keys($stages) as $stage) {
                $id = $catMap["{$stage}-{$subjectSlug}"]['id'] ?? null;
                if ($id !== null) {
                    $categoryIds[$id] = true;
                }
            }
        }

        $db = $this->db();
        $db->execute('DELETE FROM tool_category WHERE tool_id = :tid', [':tid' => $toolId]);

        $count = 0;
        foreach (array_keys($categoryIds) as $categoryId) {
            $db->execute(
                'INSERT INTO tool_category (tool_id, category_id) VALUES (:tid, :cid)',
                [':tid' => $toolId, ':cid' => $categoryId]
            );
            $count++;
        }

        return $count;
    }

    /**
     * @param mixed $value 期望为数组
     */
    private function jsonEncode(mixed $value): string
    {
        $json = json_encode(is_array($value) ? $value : [], JSON_UNESCAPED_UNICODE);

        return $json === false ? '[]' : $json;
    }

    private function hasDb(): bool
    {
        return $this->db !== null || App::hasDb();
    }

    private function db(): Database
    {
        return $this->db ?? App::db();
    }
}
