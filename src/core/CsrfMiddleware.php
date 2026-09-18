<?php
declare(strict_types=1);

namespace App\Core;

/**
 * CSRF 中间件
 *
 * 只对「会改变状态」的方法生效：GET/HEAD/OPTIONS 属安全方法，不校验，
 * 否则普通页面跳转都会失败。
 *
 * 例外路由在 routes.php 中不挂载本中间件（如 /api/track 改用限流防护）。
 */
final class CsrfMiddleware implements MiddlewareInterface
{
    /** 天然幂等、无需 CSRF 保护的方法 */
    private const SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    public function handle(Request $request, callable $next): Response
    {
        if (!in_array($request->method(), self::SAFE_METHODS, true)) {
            Csrf::verifyRequest($request);
        }

        return $next($request);
    }
}
