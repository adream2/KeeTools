<?php
declare(strict_types=1);

namespace App\Frontend\Controller;

use App\Core\App;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Services\ToolRepository;

/**
 * 前台首页
 *
 * 推荐工具（is_featured）不足时用最新工具补齐，
 * 保证冷启动（刚扫描完、还没人工推荐）首页不空。
 */
final class HomeController
{
    public function index(Request $request): Response
    {
        // 数据库未初始化时给出明确指引，而不是抛 500。
        // 首次 clone 后还没跑 init_db.php 是正常路径。
        $dbReady = App::hasDb();

        $featured = [];
        $stages = [];

        if ($dbReady) {
            $repository = new ToolRepository();
            $featured = $repository->featured(6);
            if ($featured === []) {
                $featured = $repository->latest(6);
            }
            $stages = $repository->stageCards();
        }

        $html = View::render('pages/home', [
            'pageTitle' => site_name() . ' — 免费课堂工具集',
            'dbReady'   => $dbReady,
            'featured'  => $featured,
            'stages'    => $stages,
            'friendPlacement' => 'home',
        ]);

        return Response::html($html);
    }
}
