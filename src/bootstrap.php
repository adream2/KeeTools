<?php
declare(strict_types=1);

/**
 * 应用引导
 *
 * 职责：注册自动加载 → 读 .env → 初始化各组件 → 建立数据库连接 →
 * 生产环境自检 → 返回配置好的 Router。
 *
 * 本文件不做任何输出，也不处理请求：请求流程由 public/index.php 驱动。
 * 这样 CLI 脚本（如 init_db.php）也能复用同一套引导而不触发 Web 逻辑。
 */

use App\Core\App;
use App\Core\Autoloader;
use App\Core\Config;
use App\Core\Database;
use App\Core\Env;
use App\Core\ErrorHandler;
use App\Core\Logger;
use App\Core\Router;
use App\Core\Security;
use App\Core\Session;
use App\Core\View;

// ── 1. 路径与自动加载 ──────────────────────────────
// 顺序不可颠倒：必须先注册自动加载器，否则下面 App::setBasePath()
// 会因 App\Core\App 尚未加载而抛「类不存在」。

$basePath = dirname(__DIR__);

require __DIR__ . '/core/Autoloader.php';
Autoloader::register('App\\', __DIR__);

App::setBasePath($basePath);

// ── 2. 环境变量 ────────────────────────────────────

Env::load($basePath . '/.env');

// 时区同样走环境变量：PHP 默认 UTC，不设会让日志时间与分析对不上
date_default_timezone_set(Env::get('APP_TIMEZONE', 'Asia/Shanghai') ?: 'Asia/Shanghai');

// 编码固定 UTF-8。mbstring 非必需扩展，故先做存在性判断
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

// ── 3. 日志与错误处理 ──────────────────────────────

Logger::init($basePath . '/var/logs');
ErrorHandler::register();

// ── 4. 视图与安全 ──────────────────────────────────

View::init(__DIR__ . '/views');
Security::setTrustedProxies(Env::list('TRUSTED_PROXIES'));

// 模板辅助函数（e / asset / url / icon 等）。放此处是因为其中的
// asset() 依赖 View 初始化后的资源路径约定，且模板渲染前必须已加载。
require __DIR__ . '/core/helpers.php';

// ── 5. 数据库 ──────────────────────────────────────

/**
 * 建立数据库连接。连接失败不抛异常，而是返回 null：
 * 首次部署、尚未执行 init_db.php 时属正常状态，
 * 页面据此降级为「请先初始化数据库」提示，而非直接 500。
 */
$connectDb = static function (string $path, bool $isStats): ?Database {
    try {
        return new Database(App::path($path), $isStats);
    } catch (Throwable $e) {
        Logger::warning('数据库连接失败', [
            'path'  => $path,
            'stats' => $isStats,
            'error' => $e->getMessage(),
        ]);

        return null;
    }
};

$dbPath = Env::get('DB_PATH', 'storage/app.db') ?: 'storage/app.db';
$statsDbPath = Env::get('STATS_DB_PATH', 'storage/stats.db') ?: 'storage/stats.db';

$db = $connectDb($dbPath, false);
if ($db !== null) {
    App::setDb($db);
}

$statsDb = $connectDb($statsDbPath, true);
if ($statsDb !== null) {
    App::setStatsDb($statsDb);
}

// ── 6. 会话 ────────────────────────────────────────

// 前台详情页需写 CSRF token，故会话在引导阶段统一启动
Session::start(Env::int('SESSION_LIFETIME', 7200));

// ── 7. 生产环境自检 ────────────────────────────────

if (Env::isProduction()) {
    $problems = [];

    if (Env::isDebug()) {
        $problems[] = 'APP_DEBUG 必须为 false';
    }

    $appKey = Env::get('APP_KEY', '') ?? '';
    if ($appKey === '' || preg_match('/^[0-9a-f]{64}$/i', $appKey) !== 1) {
        $problems[] = 'APP_KEY 必须为非空的 64 位十六进制串';
    }

    $passwordless = Env::bool('ADMIN_PASSWORDLESS');
    $adminPassword = Env::get('ADMIN_PASSWORD', '') ?? '';
    if (!$passwordless && $adminPassword === '') {
        $problems[] = 'ADMIN_PASSWORD 未设置（或改设 ADMIN_PASSWORDLESS=true 并配置 IP 白名单）';
    }

    // 无密码后台是高风险配置：必须限定具体 IP，
    // 否则等同于对全网开放后台
    $allowIps = Env::list('ADMIN_PASSWORDLESS_IPS');
    if ($passwordless && $allowIps === []) {
        $problems[] = 'ADMIN_PASSWORDLESS=true 时必须配置 ADMIN_PASSWORDLESS_IPS 白名单';
    }

    if ($problems !== []) {
        // 生产环境配置错误必须显式拒绝启动，不得静默降级
        Logger::log(Logger::EMERGENCY, '生产环境自检未通过', ['problems' => $problems]);
        http_response_code(500);
        header('Content-Type: text/html; charset=UTF-8');
        exit(
            '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="UTF-8">'
            . '<title>启动自检未通过</title></head><body style="font-family:system-ui;max-width:44rem;margin:4rem auto;padding:0 1rem;">'
            . '<h1 style="font-size:1.4rem;">生产环境启动自检未通过</h1><ul>'
            . implode('', array_map(
                static fn (string $p): string => '<li>' . Security::escape($p) . '</li>',
                $problems
            ))
            . '</ul><p style="color:#6b7280;">详见 var/logs/ 日志。</p></body></html>'
        );
    }
}

// ── 8. 路由 ────────────────────────────────────────

$router = new Router();
$router->load(__DIR__ . '/routes.php');

return $router;
