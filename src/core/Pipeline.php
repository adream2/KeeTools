<?php
declare(strict_types=1);

namespace App\Core;

/**
 * 中间件管道
 *
 * 洋葱模型：先注册的先进入、最后退出。用倒序包裹闭包实现，
 * 不用递归，避免中间件数量增长时的调用栈开销。
 *
 * 例：注册 [A, B]，执行顺序为 A 前 → B 前 → 目标 → B 后 → A 后。
 */
final class Pipeline
{
    /**
     * @param list<MiddlewareInterface> $middleware
     */
    public function __construct(private array $middleware = [])
    {
    }

    /**
     * 依次穿过中间件，最终抵达 $destination。
     *
     * @param callable(Request): Response $destination
     */
    public function process(Request $request, callable $destination): Response
    {
        $next = $destination;

        foreach (array_reverse($this->middleware) as $middleware) {
            $inner = $next;
            $next = static fn (Request $req): Response => $middleware->handle($req, $inner);
        }

        /** @var Response $response */
        $response = $next($request);

        return $response;
    }
}
