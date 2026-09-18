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

use App\Admin\Controller\AuthController;
use App\Admin\Controller\CategoryAdminController;
use App\Admin\Controller\DashboardController;
use App\Admin\Controller\SettingsController;
use App\Admin\Controller\ToolAdminController;
use App\Frontend\Controller\CategoryController;
use App\Frontend\Controller\HomeController;
use App\Frontend\Controller\SearchController;
use App\Frontend\Controller\ToolController;

// ── 前台 ───────────────────────────────────────────

$router->get('/', [HomeController::class, 'index'], ['csrf']);
$router->get('/tools', [SearchController::class, 'index'], ['csrf']);
$router->get('/search', [SearchController::class, 'index'], ['csrf']);
$router->get('/category/{slug}', [CategoryController::class, 'show'], ['csrf']);
$router->get('/tool/{id}', [ToolController::class, 'detail'], ['csrf']);

// P1：在线使用（iframe 同源嵌入）与统计上报
// $router->get('/tool/{id}/use', [ToolController::class, 'use']);

// ── 统计上报（不挂 csrf：改用 IP 限流 + 参数白名单，见 安全规范.md §6）──

// $router->post('/api/track', [StatsController::class, 'track']);

// ── 后台 ───────────────────────────────────────────

// 登录 / 登出不挂 auth（未登录也要能访问）
$router->get('/admin/login', [AuthController::class, 'showLogin'], ['csrf']);
$router->post('/admin/login', [AuthController::class, 'login'], ['csrf']);
$router->get('/admin/logout', [AuthController::class, 'logout']);

$router->group('/admin', ['auth', 'csrf'], function (App\Core\Router $r): void {
    $r->get('/', [DashboardController::class, 'index']);

    // 工具管理
    $r->get('/tools', [ToolAdminController::class, 'index']);
    $r->post('/tools/scan', [ToolAdminController::class, 'scan']);
    $r->post('/tools/batch', [ToolAdminController::class, 'batch']);
    $r->get('/tools/{id}/edit', [ToolAdminController::class, 'edit']);
    $r->post('/tools/{id}/update', [ToolAdminController::class, 'update']);

    // 分类管理
    $r->get('/categories', [CategoryAdminController::class, 'index']);
    $r->post('/categories/create', [CategoryAdminController::class, 'create']);
    $r->post('/categories/update', [CategoryAdminController::class, 'update']);
    $r->post('/categories/delete', [CategoryAdminController::class, 'delete']);

    // 站点设置 / 系统信息
    $r->get('/settings', [SettingsController::class, 'index']);
    $r->post('/settings', [SettingsController::class, 'save']);
    $r->get('/system', [SettingsController::class, 'system']);
    $r->post('/system/flush-overrides', [SettingsController::class, 'flushOverrides']);
});
