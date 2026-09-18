<?php
declare(strict_types=1);

namespace App\Admin\Controller;

use App\Admin\AdminController;
use App\Core\App;
use App\Core\Request;
use App\Core\Response;
use App\Core\Security;
use App\Services\NetdiskChecker;
use App\Services\NetdiskRepository;

/**
 * 网盘链接管理（仅 admin，editor 无权 —— docs/需求文档-v2.md §后台权限）
 *
 * 每工具可配多个网盘链接（类型 / URL / 提取码 / 包名），独立启停；
 * 探活支持单条与批量（到期链接）。
 */
final class NetdiskAdminController extends AdminController
{
    public function index(Request $request): Response
    {
        if (!$this->dbReady()) {
            return $this->render('netdisks', ['dbReady' => false], 'netdisks');
        }

        $rows = App::db()->fetchAll(
            'SELECT n.*, t.title AS tool_title
             FROM tool_netdisks n
             LEFT JOIN tools t ON t.tool_id = n.tool_id
             ORDER BY t.title ASC, n.id ASC'
        );
        foreach ($rows as &$row) {
            $row['id'] = (int) $row['id'];
            $row['is_active'] = (int) $row['is_active'] === 1;
            $row['type_label'] = NetdiskRepository::TYPES[$row['netdisk_type']] ?? (string) $row['netdisk_type'];
        }
        unset($row);

        $tools = App::db()->fetchAll(
            'SELECT tool_id, title FROM tools ORDER BY title ASC'
        );

        return $this->render('netdisks', [
            'pageTitle' => '网盘链接管理 — ' . site_name(),
            'dbReady'   => true,
            'links'     => $rows,
            'tools'     => $tools,
            'types'     => NetdiskRepository::TYPES,
        ], 'netdisks');
    }

    public function create(Request $request): Response
    {
        if (!$this->dbReady()) {
            return $this->redirectWith('error', '数据库未初始化', '/admin/netdisks');
        }

        $toolId = (string) ($request->post('tool_id') ?? '');
        $error = $this->validateTool($toolId) ?? $this->validateInput($request);
        if ($error !== null) {
            return $this->redirectWith('error', $error, '/admin/netdisks');
        }

        (new NetdiskRepository())->save(
            $toolId,
            (string) $request->post('netdisk_type'),
            (string) $request->post('url'),
            self::nullable($request->post('extract_code')),
            self::nullable($request->post('package_name'))
        );

        return $this->redirectWith('success', '网盘链接已添加。', '/admin/netdisks');
    }

    public function update(Request $request): Response
    {
        if (!$this->dbReady()) {
            return $this->redirectWith('error', '数据库未初始化', '/admin/netdisks');
        }

        $repository = new NetdiskRepository();
        $link = $this->resolveLink($repository, $request);
        if ($link instanceof Response) {
            return $link;
        }

        $error = $this->validateInput($request);
        if ($error !== null) {
            return $this->redirectWith('error', $error, '/admin/netdisks');
        }

        $repository->save(
            (string) $link['tool_id'],
            (string) $request->post('netdisk_type'),
            (string) $request->post('url'),
            self::nullable($request->post('extract_code')),
            self::nullable($request->post('package_name')),
            (int) $link['id']
        );

        return $this->redirectWith('success', '网盘链接已更新。', '/admin/netdisks');
    }

    public function toggle(Request $request): Response
    {
        $repository = new NetdiskRepository();
        $link = $this->resolveLink($repository, $request);
        if ($link instanceof Response) {
            return $link;
        }

        $repository->setActive((int) $link['id'], !(bool) $link['is_active']);

        return $this->redirectWith('success', $link['is_active'] ? '已停用。' : '已启用。', '/admin/netdisks');
    }

    public function delete(Request $request): Response
    {
        $repository = new NetdiskRepository();
        $link = $this->resolveLink($repository, $request);
        if ($link instanceof Response) {
            return $link;
        }

        $repository->delete((int) $link['id']);

        return $this->redirectWith('success', '网盘链接已删除。', '/admin/netdisks');
    }

    /**
     * 单条探活。
     */
    public function check(Request $request): Response
    {
        $repository = new NetdiskRepository();
        $link = $this->resolveLink($repository, $request);
        if ($link instanceof Response) {
            return $link;
        }

        $status = (new NetdiskChecker())->check((string) $link['url']);
        $repository->markChecked((int) $link['id'], $status);

        $label = ['ok' => '存活', 'invalid' => '已失效', 'unknown' => '无法判定'][$status] ?? $status;

        return $this->redirectWith(
            $status === 'ok' ? 'success' : 'warning',
            '探活结果：' . $label . '。',
            '/admin/netdisks'
        );
    }

    /**
     * 批量探活到期链接（超 24 小时未探活）。
     */
    public function checkAll(Request $request): Response
    {
        $stats = (new NetdiskChecker())->checkDue(20);

        return $this->redirectWith(
            'success',
            "批量探活完成：检查 {$stats['checked']} 条，存活 {$stats['ok']}，失效 {$stats['invalid']}。",
            '/admin/netdisks'
        );
    }

    /**
     * @return array<string, mixed>|Response
     */
    private function resolveLink(NetdiskRepository $repository, Request $request): array|Response
    {
        $id = (int) $request->attribute('id', 0);
        $link = $repository->findById($id);
        if ($link === null) {
            return $this->redirectWith('error', '链接不存在', '/admin/netdisks');
        }

        return $link;
    }

    private function validateTool(string $toolId): ?string
    {
        if (!Security::isValidToolId($toolId)) {
            return '非法工具 id';
        }

        $exists = App::db()->fetchColumn(
            'SELECT COUNT(*) FROM tools WHERE tool_id = :id',
            [':id' => $toolId]
        );

        return ((int) $exists) > 0 ? null : '工具不存在';
    }

    private function validateInput(Request $request): ?string
    {
        $type = (string) ($request->post('netdisk_type') ?? '');
        if (!isset(NetdiskRepository::TYPES[$type])) {
            return '未知网盘类型';
        }

        $url = trim((string) ($request->post('url') ?? ''));
        if (!preg_match('#^https?://#i', $url) || mb_strlen($url) > 500) {
            return '网盘链接必须为 http(s) 地址（500 字内）';
        }

        return null;
    }

    private static function nullable(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
