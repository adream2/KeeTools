<?php
declare(strict_types=1);

namespace App\Frontend\Controller;

use App\Core\App;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Services\CategoryCopy;
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

        // SEO（P4 §三）：分类页必须有实质内容（导语），不能是空列表
        $intro = CategoryCopy::intro(
            $category,
            (int) $result['total'],
            $parent !== null ? (string) $parent['name'] : null
        );

        $html = View::render('pages/category', [
            'pageTitle' => $category['name'] . '课堂工具 — ' . site_name(),
            'pageDesc'  => $intro !== '' ? mb_substr($intro, 0, 120) : ($category['name'] . '相关的免费课堂工具'),
            'pageKeywords' => implode(',', array_filter([
                (string) $category['name'],
                (string) ($parent['name'] ?? ''),
                '课堂工具',
                '免费',
                '免安装',
            ])),
            'category'  => $category,
            'parent'    => $parent,
            'chips'     => $result['chips'],
            'tools'     => $result['tools'],
            'total'     => $result['total'],
            'intro'     => $intro,
        ]);

        return Response::html($html);
    }
}
