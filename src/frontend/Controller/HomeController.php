<?php
declare(strict_types=1);

namespace App\Frontend\Controller;

use App\Core\App;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;

/**
 * 前台首页控制器
 *
 * P0 阶段只做「框架连通性验证」：渲染首页骨架，确认
 * 路由 → 控制器 → 视图 → 响应 全链路可用。
 * 真实数据（工具列表 / 分类）在 P1 阶段接入。
 */
final class HomeController
{
    public function index(Request $request): Response
    {
        // 数据库未初始化时给出明确指引，而不是抛 500。
        // 首次 clone 后还没跑 init_db.php 是正常路径。
        $dbReady = App::hasDb();

        $html = View::render('pages/home', [
            'pageTitle' => 'EduTools — 免费课堂工具集',
            'dbReady'   => $dbReady,
        ]);

        return Response::html($html);
    }
}
