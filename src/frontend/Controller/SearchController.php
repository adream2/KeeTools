<?php
declare(strict_types=1);

namespace App\Frontend\Controller;

use App\Core\App;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Services\ToolRepository;

/**
 * 搜索页（/search?q=）与全部工具页（/tools）
 *
 * 空关键词 = 全部工具列表，两个路径共用同一控制器：
 * 「全部工具」本质就是无条件的搜索，没必要做两套实现。
 */
final class SearchController
{
    public function index(Request $request): Response
    {
        if (!App::hasDb()) {
            return page_not_found('数据库未初始化，请先执行 php scripts/init_db.php');
        }

        $q = trim((string) $request->query('q', ''));
        if (mb_strlen($q) > 50) {
            $q = mb_substr($q, 0, 50);
        }

        $repository = new ToolRepository();
        $tools = $repository->search($q);
        $total = count($tools);

        // SEO：/search 是动态结果页，一律 noindex（避免垃圾页被收录）；
        // /tools（全部工具）是稳定列表页，正常收录。
        $isSearchPage = $q !== '' || str_starts_with($request->path(), '/search');

        $html = View::render('pages/search', [
            'pageTitle' => ($q !== '' ? '搜索：' . $q : '全部工具') . ' — ' . site_name(),
            'pageDesc'  => $q !== ''
                ? '搜索“' . $q . '”的结果'
                : '浏览 ' . site_name() . ' 的全部免费课堂工具，按学段与学科分类，免安装、断网可用。',
            'pageRobots' => $isSearchPage ? 'noindex,follow' : 'index,follow',
            'query'     => $q,
            'tools'     => $tools,
            'total'     => $total,
        ]);

        return Response::html($html);
    }
}
