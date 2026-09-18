<?php
declare(strict_types=1);

namespace App\Admin\Controller;

use App\Admin\AdminController;
use App\Core\App;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Services\AdminAuthService;

/**
 * 登录 / 登出
 *
 * 登录页不套后台布局（未登录无侧栏），单独渲染。
 */
final class AuthController extends AdminController
{
    public function showLogin(Request $request): Response
    {
        $html = View::render('admin/login', [
            'pageTitle'    => '登录 — ' . site_name(),
            'error'        => Session::pull('login_error'),
            'username'     => Session::pull('login_username', ''),
            'passwordless' => AdminAuthService::passwordlessEnabled(),
            'hasPassword'  => AdminAuthService::credentialsConfigured(),
        ], null);

        return Response::html($html);
    }

    public function login(Request $request): Response
    {
        if (!App::hasDb()) {
            return $this->flashLoginError('数据库未初始化，无法登录。请先执行 php scripts/init_db.php', $request);
        }

        $username = trim((string) $request->post('username', ''));
        $password = (string) $request->post('password', '');

        if ($username === '' || $password === '') {
            return $this->flashLoginError('请输入用户名和密码。', $request, $username);
        }

        $auth = new AdminAuthService();

        if ($auth->isLocked($username, $request->ip())) {
            return $this->flashLoginError('失败次数过多，账号已临时锁定，请 15 分钟后再试。', $request, $username);
        }

        if (!$auth->attempt($username, $password)) {
            return $this->flashLoginError('用户名或密码不正确。', $request, $username);
        }

        // 登录成功：换会话 ID 防固定，轮换 CSRF token
        Session::regenerate();
        Session::set('admin_user', $username);
        Session::set('admin_role', 'admin');
        Session::remove('admin_passwordless');
        Csrf::rotate();

        return Response::redirect(url('/admin'));
    }

    public function logout(Request $request): Response
    {
        Session::destroy();

        return Response::redirect(url('/admin/login'));
    }

    private function flashLoginError(string $message, Request $request, string $username = ''): Response
    {
        Session::set('login_error', $message);
        if ($username !== '') {
            Session::set('login_username', $username);
        }

        return Response::redirect(url('/admin/login'));
    }
}
