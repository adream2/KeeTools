<?php
declare(strict_types=1);

/**
 * 唯一 Web 入口（前端控制器）
 *
 * 所有 HTTP 请求都经此文件，再由 Router 分发。
 * public/ 是唯一对外暴露的目录，src/、tools/、var/、storage/ 均不可 HTTP 访问。
 *
 * 流程：bootstrap（装配）→ 路由匹配 → 中间件链 → 控制器 → 响应输出
 */

use App\Core\ErrorHandler;
use App\Core\Logger;
use App\Core\MiddlewareInterface;
use App\Core\Pipeline;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;

// 显示错误交给 ErrorHandler 统一处理，此处兜底禁止直接输出
ini_set('display_errors', '0');

// 引导应用。$router 由 bootstrap.php 返回。
try {
    $router = require dirname(__DIR__) . '/src/bootstrap.php';
} catch (Throwable $e) {
    // 引导阶段失败时 Logger / ErrorHandler 可能尚未就绪，退化为最简输出
    http_response_code(500);
    header('Content-Type: text/html; charset=UTF-8');
    exit('应用启动失败，请检查配置与日志。');
}

$request = Request::fromGlobals();

try {
    $matched = $router->dispatch($request);

    // 中间件按「名字 → 实现类」映射。用名字而非直接实例，
    // 是因为 routes.php 里写类名字符串更直观、也便于条件注册。
    $middlewareMap = [
        'csrf' => App\Core\CsrfMiddleware::class,
        'auth' => App\Admin\Middleware\AuthMiddleware::class,
        'role' => App\Admin\Middleware\RoleMiddleware::class,
    ];

    $middleware = [];
    foreach ($matched['middleware'] as $name) {
        if (!isset($middlewareMap[$name])) {
            throw new RuntimeException('未注册的中间件: ' . $name);
        }

        $class = $middlewareMap[$name];
        if (!class_exists($class)) {
            // 中间件类尚未实现（分阶段开发期间）时跳过，
            // 避免整个应用不可用；但必须记日志以便及时发现
            Logger::warning('中间件类不存在，已跳过', ['middleware' => $name, 'class' => $class]);
            continue;
        }

        $instance = new $class();
        if (!$instance instanceof MiddlewareInterface) {
            throw new RuntimeException('中间件未实现 MiddlewareInterface: ' . $class);
        }

        $middleware[] = $instance;
    }

    // 目标：把路由处理结果统一转成 Response
    $destination = static function (Request $req) use ($matched): Response {
        return App\Core\Dispatcher::invoke($matched['handler'], $req);
    };

    $response = (new Pipeline($middleware))->process($request, $destination);
    $response->send();
} catch (Throwable $e) {
    $message = $e->getMessage();

    // Router 用特定消息区分 404 / 405，避免引入额外的异常类层次
    if ($message === 'NOT_FOUND') {
        ErrorHandler::render(new RuntimeException('页面不存在'), 404);

        return;
    }

    if (str_starts_with($message, 'METHOD_NOT_ALLOWED')) {
        $allowed = substr($message, strlen('METHOD_NOT_ALLOWED:'));
        if (!headers_sent()) {
            header('Allow: ' . $allowed);
        }
        ErrorHandler::render(new RuntimeException('请求方法不被允许'), 405);

        return;
    }

    ErrorHandler::handleException($e);
}
