<?php
declare(strict_types=1);

namespace App\Admin\Controller;

use App\Admin\AdminController;
use App\Core\App;
use App\Core\Request;
use App\Core\Response;
use App\Core\Security;

/**
 * 分类管理：两级（学段 / 学科）CRUD + 排序
 *
 * 删除保护：分类下有工具关联或有子分类时禁止删除，
 * 避免 tool_category 级联清空造成前台分类静默失效。
 */
final class CategoryAdminController extends AdminController
{
    public function index(Request $request): Response
    {
        if (!$this->dbReady()) {
            return $this->render('categories', ['groups' => [], 'dbReady' => false], 'categories');
        }

        $db = App::db();
        $counts = [];
        foreach ($db->fetchAll(
            'SELECT category_id, COUNT(*) AS n FROM tool_category GROUP BY category_id'
        ) as $row) {
            $counts[(int) $row['category_id']] = (int) $row['n'];
        }

        $groups = [];
        foreach ($db->fetchAll(
            'SELECT id, name, slug, icon, sort_order FROM categories
             WHERE level = 1 ORDER BY sort_order ASC, id ASC'
        ) as $stage) {
            $stageId = (int) $stage['id'];
            $children = [];
            foreach ($db->fetchAll(
                'SELECT id, name, slug, sort_order FROM categories
                 WHERE parent_id = :pid ORDER BY sort_order ASC, id ASC',
                [':pid' => $stageId]
            ) as $subject) {
                $subject['id'] = (int) $subject['id'];
                $subject['sort_order'] = (int) $subject['sort_order'];
                $subject['count'] = $counts[$subject['id']] ?? 0;
                $children[] = $subject;
            }

            $stage['id'] = $stageId;
            $stage['sort_order'] = (int) $stage['sort_order'];
            $stage['count'] = ($counts[$stageId] ?? 0) + array_sum(
                array_map(static fn (array $c): int => $c['count'], $children)
            );
            $stage['children'] = $children;
            $groups[] = $stage;
        }

        return $this->render('categories', [
            'pageTitle' => '分类管理 — ' . site_name(),
            'groups'    => $groups,
            'dbReady'   => true,
        ], 'categories');
    }

    public function create(Request $request): Response
    {
        if (!$this->dbReady()) {
            return $this->redirectWith('error', '数据库未初始化', '/admin/categories');
        }

        [$name, $slug, $error] = $this->validatedInput($request);
        if ($error !== null) {
            return $this->redirectWith('error', $error, '/admin/categories');
        }

        $parentId = $request->int('parent_id', 0);
        if ($parentId > 0) {
            $parent = App::db()->fetch('SELECT id FROM categories WHERE id = :id AND level = 1', [':id' => $parentId]);
            if ($parent === null) {
                return $this->redirectWith('error', '父级分类不存在', '/admin/categories');
            }
        }

        $db = App::db();
        $exists = $db->fetch('SELECT slug FROM categories WHERE slug = :slug', [':slug' => $slug]);
        if ($exists !== null) {
            return $this->redirectWith('error', 'slug 已存在: ' . $slug, '/admin/categories');
        }

        $max = $db->fetchColumn(
            'SELECT COALESCE(MAX(sort_order), -1) FROM categories WHERE parent_id IS :pid',
            [':pid' => $parentId > 0 ? $parentId : null]
        );

        $db->execute(
            'INSERT INTO categories (name, slug, parent_id, level, sort_order)
             VALUES (:name, :slug, :pid, :level, :sort)',
            [
                ':name'   => $name,
                ':slug'   => $slug,
                ':pid'    => $parentId > 0 ? $parentId : null,
                ':level'  => $parentId > 0 ? 2 : 1,
                ':sort'   => (int) $max + 1,
            ]
        );

        return $this->redirectWith('success', '分类「' . $name . '」已创建。', '/admin/categories');
    }

    public function update(Request $request): Response
    {
        if (!$this->dbReady()) {
            return $this->redirectWith('error', '数据库未初始化', '/admin/categories');
        }

        $id = $request->int('id', 0);
        $category = App::db()->fetch('SELECT id, level FROM categories WHERE id = :id', [':id' => $id]);
        if ($category === null) {
            return $this->redirectWith('error', '分类不存在', '/admin/categories');
        }

        $name = trim((string) $request->post('name', ''));
        if ($name === '' || mb_strlen($name) > 20) {
            return $this->redirectWith('error', '分类名须为 1-20 个字符', '/admin/categories');
        }

        App::db()->execute(
            'UPDATE categories SET name = :name, sort_order = :sort WHERE id = :id',
            [
                ':name' => $name,
                ':sort' => max(0, $request->int('sort', 0)),
                ':id'   => $id,
            ]
        );

        return $this->redirectWith('success', '分类已更新。', '/admin/categories');
    }

    public function delete(Request $request): Response
    {
        if (!$this->dbReady()) {
            return $this->redirectWith('error', '数据库未初始化', '/admin/categories');
        }

        $id = $request->int('id', 0);
        $db = App::db();
        $category = $db->fetch('SELECT id, name, level FROM categories WHERE id = :id', [':id' => $id]);
        if ($category === null) {
            return $this->redirectWith('error', '分类不存在', '/admin/categories');
        }

        $linked = (int) $db->fetchColumn(
            'SELECT COUNT(*) FROM tool_category WHERE category_id = :id',
            [':id' => $id]
        );
        if ($linked > 0) {
            return $this->redirectWith(
                'error',
                '「' . $category['name'] . '」下有 ' . $linked . ' 个工具关联，请先解除关联（调整工具的学段/学科）再删除。',
                '/admin/categories'
            );
        }

        $children = (int) $db->fetchColumn(
            'SELECT COUNT(*) FROM categories WHERE parent_id = :id',
            [':id' => $id]
        );
        if ($children > 0) {
            return $this->redirectWith('error', '「' . $category['name'] . '」下还有 ' . $children . ' 个子分类，请先删除子分类。', '/admin/categories');
        }

        $db->execute('DELETE FROM categories WHERE id = :id', [':id' => $id]);

        return $this->redirectWith('success', '分类「' . $category['name'] . '」已删除。', '/admin/categories');
    }

    /**
     * @return array{0: string, 1: string, 2: ?string} [name, slug, error]
     */
    private function validatedInput(Request $request): array
    {
        $name = trim((string) $request->post('name', ''));
        $slug = trim((string) $request->post('slug', ''));

        if ($name === '' || mb_strlen($name) > 20) {
            return ['', '', '分类名须为 1-20 个字符'];
        }

        if (preg_match('/^[a-z0-9-]{1,64}$/', $slug) !== 1) {
            return ['', '', 'slug 只允许小写字母、数字、连字符（1-64 位），如 junior-physics'];
        }

        return [$name, $slug, null];
    }
}
