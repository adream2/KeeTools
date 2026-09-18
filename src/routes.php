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
use App\Admin\Controller\NetdiskAdminController;
use App\Admin\Controller\PackageAdminController;
use App\Admin\Controller\SettingsController;
use App\Admin\Controller\StatsAdminController;
use App\Admin\Controller\ToolAdminController;
use App\Frontend\Controller\CategoryController;
use App\Frontend\Controller\DownloadController;
use App\Frontend\Controller\FriendLinkController;
use App\Frontend\Controller\HomeController;
use App\Frontend\Controller\NetdiskController;
use App\Frontend\Controller\SearchController;
use App\Frontend\Controller\SeoController;
use App\Frontend\Controller\StatsController;
use App\Frontend\Controller\SponsorController;
use App\Frontend\Controller\ToolController;

// ── 前台 ───────────────────────────────────────────

$router->get('/', [HomeController::class, 'index'], ['csrf']);
$router->get('/tools', [SearchController::class, 'index'], ['csrf']);
$router->get('/search', [SearchController::class, 'index'], ['csrf']);
$router->get('/category/{slug}', [CategoryController::class, 'show'], ['csrf']);
$router->get('/tool/{id}', [ToolController::class, 'detail'], ['csrf']);
$router->get('/tool/{id}/use', [ToolController::class, 'use'], ['csrf']);
$router->get('/netdisk/{id}', [NetdiskController::class, 'show'], ['csrf']);
$router->get('/friend-links', [FriendLinkController::class, 'index'], ['csrf']);
$router->post('/netdisk/{id}/report', [NetdiskController::class, 'report'], ['csrf']);
$router->get('/download/{id}', [DownloadController::class, 'download'], ['csrf']);

// ── SEO（P4 §三）────────────────────────────────────
$router->get('/sitemap.xml', [SeoController::class, 'sitemap']);
$router->get('/robots.txt', [SeoController::class, 'robots']);

// ── 统计上报（不挂 csrf：改用 IP 限流 + 参数白名单，见 安全规范.md §6）──

$router->post('/api/track', [StatsController::class, 'track']);

// ── 运营（P1 §八：赞助页与「我扫了这码」匿名上报）─────────────

$router->get('/sponsor', [SponsorController::class, 'index'], ['csrf']);
$router->post('/api/sponsor/click', [SponsorController::class, 'click']);

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
    $r->post('/tools/{id}/published', [ToolAdminController::class, 'setPublished']);
    $r->get('/tools/{id}/edit', [ToolAdminController::class, 'edit']);
    $r->post('/tools/{id}/update', [ToolAdminController::class, 'update']);

    // 分类管理
    $r->get('/categories', [CategoryAdminController::class, 'index']);
    $r->post('/categories/create', [CategoryAdminController::class, 'create']);
    $r->post('/categories/update', [CategoryAdminController::class, 'update']);
    $r->post('/categories/delete', [CategoryAdminController::class, 'delete']);

    // 网盘链接管理（仅 admin：editor 无权访问）
    $r->group('', ['role'], function (App\Core\Router $r): void {
        $r->get('/netdisks', [NetdiskAdminController::class, 'index']);
        $r->post('/netdisks/create', [NetdiskAdminController::class, 'create']);
        $r->post('/netdisks/check-all', [NetdiskAdminController::class, 'checkAll']);
        $r->post('/netdisks/{id}/update', [NetdiskAdminController::class, 'update']);
        $r->post('/netdisks/{id}/toggle', [NetdiskAdminController::class, 'toggle']);
        $r->post('/netdisks/{id}/delete', [NetdiskAdminController::class, 'delete']);
        $r->post('/netdisks/{id}/check', [NetdiskAdminController::class, 'check']);

        // 离线包打包（P3，仅 admin）
        $r->get('/package', [PackageAdminController::class, 'index']);
        $r->post('/package/create', [PackageAdminController::class, 'create']);
        $r->get('/package/status', [PackageAdminController::class, 'status']);
        $r->get('/package/{id}/download', [PackageAdminController::class, 'download']);
        $r->post('/package/{id}/delete', [PackageAdminController::class, 'delete']);
        $r->post('/package/{id}/regen', [PackageAdminController::class, 'regen']);
    });

    // 统计看板（editor 只读可访问）
    $r->get('/stats', [StatsAdminController::class, 'index']);

    // 站点设置 / 系统信息（分区 Tab，仅 admin）
    $r->group('', ['role'], function (App\Core\Router $r): void {
        $r->get('/settings', [SettingsController::class, 'index']);
        $r->post('/settings', [SettingsController::class, 'save']);
        $r->get('/settings/footer', [SettingsController::class, 'footer']);
        $r->post('/settings/footer', [SettingsController::class, 'saveFooter']);
        $r->get('/settings/links', [SettingsController::class, 'links']);
        $r->post('/settings/links/create', [SettingsController::class, 'createLink']);
        $r->post('/settings/links/batch', [SettingsController::class, 'batchLinks']);
        $r->post('/settings/links/{id}/update', [SettingsController::class, 'updateLink']);
        $r->post('/settings/links/{id}/delete', [SettingsController::class, 'deleteLink']);
        $r->get('/settings/ads', [SettingsController::class, 'ads']);
        $r->post('/settings/ads', [SettingsController::class, 'saveAds']);
        $r->get('/settings/announce', [SettingsController::class, 'announce']);
        $r->post('/settings/announce/save-settings', [SettingsController::class, 'saveAnnounceSettings']);
        $r->post('/settings/announce/create', [SettingsController::class, 'createAnnounce']);
        $r->post('/settings/announce/{id}/toggle', [SettingsController::class, 'toggleAnnounce']);
        $r->post('/settings/announce/{id}/delete', [SettingsController::class, 'deleteAnnounce']);
        $r->get('/settings/sponsor', [SettingsController::class, 'sponsor']);
        $r->post('/settings/sponsor', [SettingsController::class, 'saveSponsor']);
        $r->post('/settings/sponsor/thanks/create', [SettingsController::class, 'createThanks']);
        $r->post('/settings/sponsor/thanks/{id}/delete', [SettingsController::class, 'deleteThanks']);
        $r->get('/system', [SettingsController::class, 'system']);
        $r->post('/system/flush-overrides', [SettingsController::class, 'flushOverrides']);
    });
});
