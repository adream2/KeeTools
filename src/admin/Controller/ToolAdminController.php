<?php
declare(strict_types=1);

namespace App\Admin\Controller;

use App\Admin\AdminController;
use App\Core\App;
use App\Core\Request;
use App\Core\Response;
use App\Core\Security;
use App\Core\Session;

/**
 * 工具管理：列表 / 批量更新（推荐 / 上架 / 排序）/ 单个上下架
 *
 * 工具入库走 bootstrap 自动同步（manifest mtime 变化才重扫），无手动扫描。
 * manifest 镜像字段（标题 / 描述 / 学段 / 学科等）不做后台编辑（2026-09-19 定稿）：
 * manifest 是唯一真源，改动直接改文件，下次请求自动同步。
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
}
