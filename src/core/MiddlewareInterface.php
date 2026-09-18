<?php
declare(strict_types=1);

namespace App\Core;

/**
 * 中间件契约
 *
 * 每个中间件决定：放行（调用 $next）、拦截（直接返回响应）、
 * 或处理后再交接。返回 Response 而非 echo，便于组合与测试。
 */
interface MiddlewareInterface
{
    /**
     * @param Request  $request 当前请求
     * @param callable $next    下一环，签名 fn(Request): Response
     */
    public function handle(Request $request, callable $next): Response;
}
