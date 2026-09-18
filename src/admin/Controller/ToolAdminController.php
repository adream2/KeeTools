<?php
declare(strict_types=1);

namespace App\Admin\Controller;

use App\Admin\AdminController;
use App\Core\App;
use App\Core\Request;
use App\Core\Response;
use App\Core\Security;
use App\Core\Session;
use App\Services\ManifestWriter;
use App\Services\ToolRepository;
use App\Services\ToolScanner;

/**
 * 工具管理：列表 / 扫描同步 / 编辑元数据（回写 manifest）/ 批量更新
 *
 * 用户态字段（推荐 / 上架 / 排序）走批量保存表单；
 * manifest 镜像字段走编辑页，经 ManifestWriter 落盘后立即重扫该工具入库。
 */
final class ToolAdminController extends AdminController
{
    public function index(Request $request): Response
    {
        if (!$this->dbReady()) {
            return $this->render('tools', ['tools' => [], 'dbReady' => false], 'tools');
        }

        $rows = App::db()->fetchAll(
            'SELECT tool_id, title, version, type, is_featured, is_published, sort_order,
                    meta_mismatch, dir_path, updated_at
             FROM tools
             ORDER BY is_featured DESC, sort_order ASC, updated_at DESC'
        );

        return $this->render('tools', [
            'pageTitle' => '工具管理 — ' . site_name(),
            'tools'     => $rows,
            'dbReady'   => true,
        ], 'tools');
    }

    /**
     * 扫描同步（tools/ → DB）。
     */
    public function scan(Request $request): Response
    {
        if (!$this->dbReady()) {
            return $this->redirectWith('error', '数据库未初始化', '/admin/tools');
        }

        $report = (new ToolScanner())->scan();

        $parts = [];
        if ($report['created'] !== []) {
            $parts[] = '新增 ' . count($report['created']);
        }
        if ($report['updated'] !== []) {
            $parts[] = '更新 ' . count($report['updated']);
        }
        if ($report['failed'] !== []) {
            $parts[] = '失败 ' . count($report['failed']);
        }
        if ($report['missing'] !== []) {
            $parts[] = '目录缺失 ' . count($report['missing']);
        }
        if ($report['meta_mismatch'] !== []) {
            $parts[] = '版本不一致 ' . count($report['meta_mismatch']);
        }

        $message = '扫描完成：共 ' . $report['scanned'] . ' 个工具'
            . ($parts !== [] ? '（' . implode('，', $parts) . '）' : '');

        // 失败明细进日志，页面只给摘要，避免 flash 过长
        foreach ($report['failed'] as $toolId => $errors) {
            App\Core\Logger::warning('扫描失败: ' . $toolId, ['errors' => $errors]);
        }

        $type = ($report['failed'] !== []) ? 'warning' : 'success';

        return $this->redirectWith($type, $message, '/admin/tools');
    }

    /**
     * 编辑表单。
     */
    public function edit(Request $request): Response
    {
        if (!$this->dbReady()) {
            return $this->redirectWith('error', '数据库未初始化', '/admin');
        }

        $toolId = (string) $request->attribute('id', '');
        if (!Security::isValidToolId($toolId)) {
            return $this->redirectWith('error', '非法工具 id', '/admin/tools');
        }

        $tool = (new ToolRepository())->findAny($toolId);
        if ($tool === null) {
            return $this->redirectWith('error', '工具不存在: ' . $toolId, '/admin/tools');
        }

        return $this->render('tool-edit', [
            'pageTitle'  => '编辑：' . $tool['title'] . ' — ' . site_name(),
            'tool'       => $tool,
            'writable'   => (new ManifestWriter())->isWritable($toolId),
            'gradeOptions' => self::GRADE_OPTIONS,
            'subjectOptions' => self::SUBJECT_OPTIONS,
        ], 'tools');
    }

    /**
     * 保存编辑：回写 manifest（或降级 override）→ 重扫入库。
     */
    public function update(Request $request): Response
    {
        if (!$this->dbReady()) {
            return $this->redirectWith('error', '数据库未初始化', '/admin/tools');
        }

        $toolId = (string) $request->attribute('id', '');
        if (!Security::isValidToolId($toolId)) {
            return $this->redirectWith('error', '非法工具 id', '/admin/tools');
        }

        $title = trim((string) $request->post('title', ''));
        if ($title === '') {
            return $this->redirectWith('error', '标题不能为空', '/admin/tools/' . rawurlencode($toolId) . '/edit');
        }

        $fields = [
            'title'       => $title,
            'description' => trim((string) $request->post('description', '')),
            'author'      => trim((string) $request->post('author', '')),
            'license'     => trim((string) $request->post('license', 'free')) ?: 'free',
            'family'      => trim((string) $request->post('family', '')) ?: null, // null = 从 manifest 移除
            'screen'      => in_array($request->post('screen', 'large'), ['large', 'any'], true)
                ? (string) $request->post('screen', 'large')
                : 'large',
            'stats_enabled' => $request->post('stats_enabled') !== null,
            'grade_range'   => self::filterChoices($request->allPost()['grade_range'] ?? [], array_keys(self::GRADE_OPTIONS)),
            'subjects'      => self::filterChoices($request->allPost()['subjects'] ?? [], self::SUBJECT_OPTIONS),
            'tags'          => self::parseTags((string) $request->post('tags', '')),
            'updated_at'    => date('Y-m-d'),
        ];

        $writer = new ManifestWriter();
        $status = $writer->write($toolId, $fields);

        // 立即重扫该工具，让 DB 与 manifest 保持一致
        $scan = (new ToolScanner())->scanOne($toolId);

        if ($scan['status'] === 'skipped') {
            App\Core\Logger::error('编辑后重扫失败: ' . $toolId, ['errors' => $scan['errors']]);

            return $this->redirectWith(
                'error',
                '保存了 manifest，但校验未通过，未同步到数据库：' . implode('；', array_slice($scan['errors'], 0, 3)),
                '/admin/tools'
            );
        }

        if ($status === 'override') {
            return $this->redirectWith(
                'warning',
                'tools/ 目录不可写，修改已暂存到数据库覆盖层（降级模式），恢复可写后可在「系统信息」页一键落盘。',
                '/admin/tools'
            );
        }

        return $this->redirectWith('success', '已保存并回写 manifest.json，数据库已同步。', '/admin/tools');
    }

    /**
     * 批量保存：排序 / 推荐 / 上下架。
     */
    public function batch(Request $request): Response
    {
        if (!$this->dbReady()) {
            return $this->redirectWith('error', '数据库未初始化', '/admin/tools');
        }

        $post = $request->allPost();
        $sorts = is_array($post['sort'] ?? null) ? $post['sort'] : [];
        $featured = is_array($post['featured'] ?? null) ? array_flip(array_keys($post['featured'])) : [];
        $published = is_array($post['published'] ?? null) ? array_flip(array_keys($post['published'])) : [];

        $updated = 0;
        $db = App::db();
        foreach (array_keys($sorts) as $toolId) {
            if (!is_string($toolId) || !Security::isValidToolId($toolId)) {
                continue;
            }

            $db->execute(
                'UPDATE tools
                 SET sort_order = :sort,
                     is_featured = :featured,
                     is_published = :published
                 WHERE tool_id = :tool_id',
                [
                    ':sort'      => max(0, (int) $sorts[$toolId]),
                    ':featured'  => isset($featured[$toolId]) ? 1 : 0,
                    ':published' => isset($published[$toolId]) ? 1 : 0,
                    ':tool_id'   => $toolId,
                ]
            );
            $updated++;
        }

        return $this->redirectWith('success', '已更新 ' . $updated . ' 个工具的排序 / 推荐 / 上架状态。', '/admin/tools');
    }

    /**
     * 单个工具上架 / 下架切换（P4 §四：零使用工具「下线或重做」）。
     *
     * 为什么不复用 batch()：那个端点要求提交全量表单，单独调用会把
     * 其他工具的排序与推荐位清零。
     */
    public function setPublished(Request $request): Response
    {
        if (!$this->dbReady()) {
            return $this->redirectWith('error', '数据库未初始化', '/admin/tools');
        }

        $toolId = (string) $request->attribute('id', '');
        if (!Security::isValidToolId($toolId)) {
            return $this->redirectWith('error', '工具 id 非法', '/admin/tools');
        }

        // 只允许跳回后台路径，防开放重定向
        $back = (string) ($request->post('back') ?? '/admin/tools');
        if (!str_starts_with($back, '/admin/')) {
            $back = '/admin/tools';
        }

        $exists = App::db()->fetch('SELECT tool_id FROM tools WHERE tool_id = :id', [':id' => $toolId]);
        if ($exists === null) {
            return $this->redirectWith('error', '工具不存在：' . $toolId, $back);
        }

        $value = $request->post('value') === '1' ? 1 : 0;
        App::db()->execute(
            'UPDATE tools SET is_published = :published WHERE tool_id = :id',
            [':published' => $value, ':id' => $toolId]
        );

        return $this->redirectWith(
            'success',
            ($value === 1 ? '已重新上架：' : '已下架：') . $toolId . '（目录与 manifest 未改动）',
            $back
        );
    }

    /**
     * 把「降级覆盖」落回 manifest 文件（tools/ 恢复可写后）。
     */
    public function flushOverrides(Request $request): Response
    {
        $writer = new ManifestWriter();
        $db = App::db();
        $rows = $db->fetchAll('SELECT tool_id FROM tool_overrides');

        $flushed = 0;
        $failed = 0;
        foreach ($rows as $row) {
            $toolId = (string) $row['tool_id'];
            if ($writer->flushOverride($toolId)) {
                $flushed++;
                (new ToolScanner())->scanOne($toolId);
            } else {
                $failed++;
            }
        }

        if ($rows === []) {
            return $this->redirectWith('info', '当前没有待落盘的降级覆盖。', '/admin/system');
        }

        $type = $failed > 0 ? 'warning' : 'success';
        $message = "已落盘 {$flushed} 个工具的覆盖" . ($failed > 0 ? "，{$failed} 个失败（目录仍不可写）" : '。');

        return $this->redirectWith($type, $message, '/admin/system');
    }

    private static function parseTags(string $raw): array
    {
        $tags = [];
        foreach (explode(',', $raw) as $tag) {
            $tag = trim($tag);
            if ($tag !== '') {
                $tags[] = $tag;
            }
        }

        return array_values(array_unique($tags));
    }

    /**
     * @param mixed  $values  表单数组（可能非数组）
     * @param list<string> $allowed
     * @return list<string>
     */
    private static function filterChoices(mixed $values, array $allowed): array
    {
        if (!is_array($values)) {
            return [];
        }

        $picked = [];
        foreach ($values as $value) {
            if (is_string($value) && in_array($value, $allowed, true)) {
                $picked[] = $value;
            }
        }

        return array_values(array_unique($picked));
    }

    private const GRADE_OPTIONS = [
        '1-2'   => '小学低年级',
        '3-4'   => '小学中年级',
        '5-6'   => '小学高年级',
        '1-6'   => '小学全段',
        '7-9'   => '初中',
        '10-12' => '高中',
        '1-12'  => '全学段（通用）',
    ];

    private const SUBJECT_OPTIONS = [
        '通用', '语文', '数学', '英语', '物理', '化学', '生物',
        '政治', '历史', '地理', '科学', '信息技术', '体育', '音乐', '美术',
    ];
}
