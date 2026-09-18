<?php
declare(strict_types=1);

namespace App\Frontend\Controller;

use App\Core\Request;
use App\Core\Response;
use App\Services\StatsService;

/**
 * 统计上报端点（POST /api/track）
 *
 * 不挂 CSRF（无会话语义、纯匿名计数），防线改为：
 * 参数白名单 + IP 限流 + use_online 去重（见 StatsService）。
 *
 * 无论成功失败一律返回 202 空体：不向前台泄露任何校验细节，
 * 也让 keepalive 请求在页面卸载时无副作用。
 */
final class StatsController
{
    public function track(Request $request): Response
    {
        if ($request->header('Content-Type') !== null
            && str_contains((string) $request->header('Content-Type'), 'application/json')
        ) {
            $payload = $request->json();
        } else {
            $payload = $request->allPost();
        }

        if ($payload === [] && $request->rawBody() !== null) {
            // fetch keepalive + JSON body 的兜底解析
            $payload = $request->json();
        }

        StatsService::trackFromRequest($payload);

        return (new Response('', 202))
            ->withHeader('Content-Type', 'text/plain; charset=UTF-8');
    }
}
