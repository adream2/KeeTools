<?php
declare(strict_types=1);

namespace App\Admin\Middleware;

use App\Core\Env;
use App\Core\MiddlewareInterface;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\AdminAuthService;

/**
 * 后台登录中间件
 *
 * 已登录放行；未登录时若命中无密码白名单则就地建立会话（记入
 * session 供布局显示醒目警告条），否则跳转登录页。
 */
final class AuthMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        if (Session::has('admin_user')) {
            return $next($request);
        }

        // 无密码后台：IP 白名单命中即视为已认证
        if (AdminAuthService::passwordlessAllowed()) {
            Session::regenerate();
            Session::set('admin_user', Env::get('ADMIN_USERNAME', 'admin') ?: 'admin');
            Session::set('admin_role', 'admin');
            Session::set('admin_passwordless', true);

            return $next($request);
        }

        return Response::redirect(url('/admin/login'));
    }
}
