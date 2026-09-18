<?php
declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * 路由处理器调用器
 *
 * 支持两种处理器形态：
 *   1. 闭包：直接调用
 *   2. [控制器类名, 方法名]：实例化后调用
 *
 * 约定控制器方法签名为 fn(Request): Response，返回值必须是 Response；
 * 返回其它类型视为编程错误并抛出，避免控制器误 echod 后返回 null
 * 导致页面半截输出难以排查。
 */
final class Dispatcher
{
    /**
     * @param callable|array{0: class-string, 1: string} $handler
     */
    public static function invoke(callable|array $handler, Request $request): Response
    {
        if (is_array($handler)) {
            [$class, $method] = $handler;

            if (!class_exists($class)) {
                throw new RuntimeException('控制器不存在: ' . $class);
            }

            $controller = new $class();
            if (!method_exists($controller, $method)) {
                throw new RuntimeException('控制器方法不存在: ' . $class . '::' . $method);
            }

            $result = $controller->{$method}($request);
        } else {
            $result = $handler($request);
        }

        if (!$result instanceof Response) {
            throw new RuntimeException(
                '控制器必须返回 Response 实例，实际返回: '
                . (is_object($result) ? $result::class : gettype($result))
            );
        }

        return $result;
    }
}
