<?php
declare(strict_types=1);

namespace App\Frontend\Controller;

use App\Core\App;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Core\Security;
use App\Core\View;
use App\Services\NetdiskRepository;
use App\Services\StatsService;
use App\Services\ToolRepository;

/**
 * 网盘中间页（/netdisk/{id}?type=）
 *
 * 转化道具：链接 + 提取码 + 倒计时（防直接抓链，也传递「认真做站」信号）。
 * 页面加载即计 netdisk_click（区分网盘类型，服务端直记）。
 * 失效反馈写入 check_status = reported 并记日志，后台网盘管理可见。
 */
final class NetdiskController
{
    public function show(Request $request): Response
    {
        $tool = $this->resolveTool($request);
        if ($tool instanceof Response) {
            return $tool;
        }
        $toolId = (string) $tool['tool_id'];

        $repository = new NetdiskRepository();
        $all = $repository->forTool($toolId);
        if ($all === []) {
            return page_not_found('该工具暂未配置网盘链接');
        }

        $type = (string) ($request->query('type') ?? '');
        if ($type !== '' && !isset(NetdiskRepository::TYPES[$type])) {
            return page_not_found('未知网盘类型');
        }

        // 未指定类型 = 主推（排序第一）网盘
        $current = null;
        foreach ($all as $item) {
            if ($type === '' || $item['netdisk_type'] === $type) {
                $current = $item;
                break;
            }
        }
        if ($current === null) {
            // 指定类型失效/不存在 → 落到第一个活跃链接
            $current = $all[0];
        }

        StatsService::record('netdisk_click', $toolId, (string) $current['netdisk_type']);

        $html = View::render('pages/netdisk', [
            'pageTitle' => '获取 ' . $tool['title'] . ' — ' . site_name(),
            'pageDesc'  => '获取 ' . $tool['title'] . ' 离线合集包：' . $current['type_label'] . ' 链接与提取码',
            'tool'      => $tool,
            'current'   => $current,
            'netdisks'  => $all,
        ]);

        return Response::html($html);
    }

    /**
     * 链接失效反馈（POST /netdisk/{id}/report）。
     *
     * 匿名可提交（教师不注册），限流 + CSRF 双防线。
     * 通知管理员的落点：check_status=reported（后台列表标红）+ 日志。
     */
    public function report(Request $request): Response
    {
        $tool = $this->resolveTool($request);
        if ($tool instanceof Response) {
            return $tool;
        }
        $toolId = (string) $tool['tool_id'];

        if (!\App\Services\RateLimiter::hit('netdisk-report', 5, 300)) {
            return Response::text('反馈过于频繁，请稍后再试', 429);
        }

        $type = (string) ($request->post('type') ?? '');
        if (!isset(NetdiskRepository::TYPES[$type])) {
            return Response::text('未知网盘类型', 400);
        }

        $repository = new NetdiskRepository();
        $link = $repository->findActive($toolId, $type);
        if ($link !== null) {
            $repository->markChecked((int) $link['id'], 'reported');
            Logger::info('网盘链接失效反馈', [
                'tool' => $toolId,
                'type' => $type,
                'ip'   => Security::hashIp(Security::clientIp()),
            ]);
        }

        $html = View::render('pages/netdisk-reported', [
            'pageTitle' => '反馈已收到 — ' . site_name(),
            'tool'      => $tool,
            'typeLabel' => NetdiskRepository::TYPES[$type],
        ]);

        return Response::html($html);
    }

    /**
     * @return array<string, mixed>|Response
     */
    private function resolveTool(Request $request): array|Response
    {
        if (!App::hasDb()) {
            return page_not_found('数据库未初始化，请先执行 php scripts/init_db.php');
        }

        $toolId = (string) $request->attribute('id', '');
        if (!Security::isValidToolId($toolId)) {
            return page_not_found('工具不存在');
        }

        $tool = (new ToolRepository())->find($toolId);
        if ($tool === null) {
            return page_not_found('工具不存在: ' . $toolId);
        }

        return $tool;
    }
}
