<?php
declare(strict_types=1);

namespace App\Admin\Middleware;

use App\Core\MiddlewareInterface;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use RuntimeException;

/**
 * 角色中间件
 *
 * P0 只有一个角色（.env 单管理员 = admin），挂载本中间件的路由
 * 要求 admin 角色；editor 角色在 P1 多账号落地后才有意义。
 */
final class RoleMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        if (Session::get('admin_role') === 'admin') {
            return $next($request);
        }

        $html = View::render('pages/error', [
            'status'     => 403,
            'message'    => '没有权限执行此操作',
            'exception'  => new RuntimeException('FORBIDDEN'),
            'showDetail' => false,
        ], null);

        return Response::html($html, 403);
    }
}
