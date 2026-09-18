<?php
declare(strict_types=1);

namespace App\Frontend\Controller;

use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Services\RateLimiter;
use App\Services\SiteOps;
use App\Services\StatsService;

/**
 * 赞助页（/sponsor/）+ 「我扫了这码」匿名点击上报
 *
 * 最小实现（docs/站点运营模块设计.md §六）：收款码双卡 + 鸣谢 + 匿名事件。
 * 不做支付对接 / 订单（P5 可选）。sponsor_enabled=false 时 404（零痕迹）。
 */
final class SponsorController
{
    public function index(Request $request): Response
    {
        $sponsor = SiteOps::sponsor();
        if ($sponsor === null) {
            return page_not_found('页面不存在');
        }

        $html = View::render('pages/sponsor', [
            'pageTitle' => $sponsor['title'] . ' — ' . site_name(),
            'pageDesc'  => $sponsor['desc'] !== '' ? $sponsor['desc'] : '支持课工具持续更新',
            'sponsor'   => $sponsor,
            'thanks'    => SiteOps::sponsorThanks(),
        ]);

        return Response::html($html);
    }

    /**
     * 「我扫了这码」匿名上报（POST /api/sponsor/click）。
     *
     * 不收集任何个人信息，仅计数。限流同 track。
     */
    public function click(Request $request): Response
    {
        if (!RateLimiter::hit('sponsor-click', 10, 300)) {
            return (new Response('', 429))->withHeader('Content-Type', 'text/plain; charset=UTF-8');
        }

        StatsService::record('sponsor_click');

        return (new Response('', 202))->withHeader('Content-Type', 'text/plain; charset=UTF-8');
    }
}
