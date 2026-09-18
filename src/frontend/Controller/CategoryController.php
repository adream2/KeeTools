<?php
declare(strict_types=1);

namespace App\Frontend\Controller;

use App\Core\App;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Services\ToolRepository;

/**
 * 分类页（/category/{slug}）
 *
 * slug 同时支持一级学段（junior，含其下全部学科工具）
 * 与二级学科（junior-physics，精确匹配），由 Repository 内部区分。
 */
final class CategoryController
{
    public function show(Request $request): Response
    {
        if (!App::hasDb()) {
            return page_not_found('数据库未初始化，请先执行 php scripts/init_db.php');
        }

        /** @var string $slug 路由参数（Router 只注入非空段） */
        $slug = (string) $request->attribute('slug', '');
        if (!preg_match('/^[a-z0-9-]{1,64}$/', $slug)) {
            return page_not_found('分类不存在');
        }

        $repository = new ToolRepository();
        $result = $repository->byCategorySlug($slug);
        if ($result === null) {
            return page_not_found('分类不存在: ' . $slug);
        }

        $category = $result['category'];

        // 面包屑父级：二级学科页显示所属学段
        $parent = null;
        if ($category['parent_id'] !== null) {
            $parent = App::db()->fetch(
                'SELECT name, slug FROM categories WHERE id = :id',
                [':id' => $category['parent_id']]
            );
        }

        $html = View::render('pages/category', [
            'pageTitle' => $category['name'] . ' — ' . site_name(),
            'pageDesc'  => $category['name'] . '相关的免费课堂工具',
            'category'  => $category,
            'parent'    => $parent,
            'chips'     => $result['chips'],
            'tools'     => $result['tools'],
            'total'     => $result['total'],
        ]);

        return Response::html($html);
    }
}
