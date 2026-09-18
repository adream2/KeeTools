<?php
declare(strict_types=1);

namespace App\Admin;

use App\Core\App;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;

/**
 * 后台控制器基类
 *
 * 统一提供：布局渲染（带侧栏激活态）、数据库就绪守卫、
 * flash 消息与 POST 后重定向。
 */
abstract class AdminController
{
    /**
     * 渲染后台页面（套用后台布局）。
     *
     * @param array<string, mixed> $data
     */
    protected function render(string $view, array $data = [], string $active = ''): Response
    {
        $html = View::render(
            'admin/' . $view,
            $data + ['adminActive' => $active],
            'layout/admin'
        );

        return Response::html($html);
    }

    /**
     * 数据库是否可用（不可用时页面降级为提示，不 500）。
     */
    protected function dbReady(): bool
    {
        return App::hasDb();
    }

    /**
     * 写 flash 后重定向（POST → 302 → GET 标准流）。
     */
    protected function redirectWith(string $type, string $message, string $to): Response
    {
        Session::flash($type, $message);

        return Response::redirect(url($to));
    }
}
