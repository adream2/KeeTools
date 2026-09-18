<?php
declare(strict_types=1);

/**
 * 路由表
 *
 * 前台：仅挂载 csrf（对 POST 生效）
 * 后台：session 已在 bootstrap 启动，故只需 auth → role → csrf
 *
 * 处理器两种写法：
 *   [App\Frontend\Controller\HomeController::class, 'index']
 *   闭包（仅适合极简端点或临时调试）
 *
 * @var App\Core\Router $router
 */

use App\Frontend\Controller\HomeController;

// ── 前台 ───────────────────────────────────────────

$router->get('/', [HomeController::class, 'index'], ['csrf']);

// 占位：分类 / 搜索 / 详情 / 在线使用在 P1 阶段实现
// $router->get('/category/{slug}', [CategoryController::class, 'show']);
// $router->get('/search', [SearchController::class, 'index']);
// $router->get('/tool/{id}', [ToolController::class, 'detail']);
// $router->get('/tool/{id}/use', [ToolController::class, 'use']);

// ── 统计上报（不挂 csrf：改用 IP 限流 + 参数白名单，见 安全规范.md §6）──

// $router->post('/api/track', [StatsController::class, 'track']);

// ── 后台 ───────────────────────────────────────────

// $router->group('/admin', ['auth', 'csrf'], function (App\Core\Router $r): void {
//     $r->get('/', [DashboardController::class, 'index']);
//     $r->get('/login', [AuthController::class, 'showLogin']);
// });

// 后台登录页不挂 auth（未登录也要能访问），单独注册
// $router->get('/admin/login', [AuthController::class, 'showLogin'], ['csrf']);
// $router->post('/admin/login', [AuthController::class, 'login'], ['csrf']);
