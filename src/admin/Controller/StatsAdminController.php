<?php
declare(strict_types=1);

namespace App\Admin\Controller;

use App\Admin\AdminController;
use App\Core\App;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Services\StatsService;

/**
 * 统计看板（docs/需求文档-v2.md §10.3）
 *
 * 渲染前先 flush 缓冲：即使 cron 未配置，看板数据也保证新鲜。
 * editor 只读可访问（§后台权限：统计看板 ✅ editor）。
 */
final class StatsAdminController extends AdminController
{
    public function index(Request $request): Response
    {
        if (!App::hasStatsDb()) {
            return $this->render('stats', ['ready' => false], 'stats');
        }

        StatsService::flush();
        $db = App::statsDb();

        // ── 总览 ────────────────────────────────────
        $totals = ['view' => 0, 'use_online' => 0, 'netdisk_click' => 0, 'download_direct' => 0, 'sponsor_click' => 0];
        foreach ($db->fetchAll(
            'SELECT event_type, COUNT(*) AS n FROM stats GROUP BY event_type'
        ) as $row) {
            $totals[(string) $row['event_type']] = (int) $row['n'];
        }

        // ── 网盘点击排行（变现核心）────────────────
        $netdiskRanking = $db->fetchAll(
            "SELECT COALESCE(netdisk_type, '?') AS type, COUNT(*) AS n
             FROM stats WHERE event_type = 'netdisk_click'
             GROUP BY netdisk_type ORDER BY n DESC"
        );

        // ── 工具排行（各维度 Top 10，带工具名）──────
        // tools 表在业务库、stats 在统计库，无法跨库 JOIN：
        // 统计侧只取 tool_id，名称由业务库批量映射（工具被删时回退 tool_id）
        $toolRanking = [];
        $toolIds = [];
        foreach (['view', 'use_online', 'netdisk_click'] as $event) {
            $rows = $db->fetchAll(
                "SELECT tool_id, COUNT(*) AS n FROM stats
                 WHERE event_type = :event AND tool_id != ''
                 GROUP BY tool_id ORDER BY n DESC LIMIT 10",
                [':event' => $event]
            );
            $toolRanking[$event] = array_map(
                static fn (array $r): array => ['tool_id' => (string) $r['tool_id'], 'n' => (int) $r['n']],
                $rows
            );
            foreach ($toolRanking[$event] as $row) {
                $toolIds[] = $row['tool_id'];
            }
        }

        $toolTitles = [];
        if ($toolIds !== [] && App::hasDb()) {
            $uniqueIds = array_values(array_unique($toolIds));
            $placeholders = implode(', ', array_map(
                static fn (int $i): string => ':tid' . $i,
                array_keys($uniqueIds)
            ));
            $params = [];
            foreach ($uniqueIds as $i => $tid) {
                $params[':tid' . $i] = $tid;
            }
            foreach (App::db()->fetchAll(
                'SELECT tool_id, title FROM tools WHERE tool_id IN (' . $placeholders . ')',
                $params
            ) as $row) {
                $toolTitles[(string) $row['tool_id']] = (string) $row['title'];
            }
        }
        foreach ($toolRanking as &$ranking) {
            foreach ($ranking as &$row) {
                $row['title'] = $toolTitles[$row['tool_id']] ?? $row['tool_id'];
            }
            unset($row);
        }
        unset($ranking);

        // ── 近 30 天趋势 ────────────────────────────
        $trend = $this->dailyTrend($db, 30);

        // ── 转化率：浏览 → 网盘点击（按工具维度粗算）──
        $conversion = $totals['view'] > 0
            ? round($totals['netdisk_click'] / $totals['view'] * 100, 1)
            : null;

        // ── 失效反馈待处理 ──────────────────────────
        $reported = 0;
        if (App::hasDb()) {
            $reported = (int) App::db()->fetchColumn(
                "SELECT COUNT(*) FROM tool_netdisks WHERE check_status = 'reported'"
            );
        }

        return $this->render('stats', [
            'pageTitle'      => '统计看板 — ' . site_name(),
            'ready'          => true,
            'totals'         => $totals,
            'netdiskRanking' => $netdiskRanking,
            'toolRanking'    => $toolRanking,
            'trend'          => $trend,
            'conversion'     => $conversion,
            'reported'       => $reported,
        ], 'stats');
    }

    /**
     * 近 N 天按日事件计数（缺失日期补 0，趋势图不断线）。
     *
     * @return list<array{date: string, view: int, use_online: int, netdisk_click: int}>
     */
    private function dailyTrend(Database $db, int $days): array
    {
        $since = date('Y-m-d 00:00:00', time() - ($days - 1) * 86400);

        $map = [];
        foreach ($db->fetchAll(
            "SELECT substr(created_at, 1, 10) AS day, event_type, COUNT(*) AS n
             FROM stats
             WHERE created_at >= :since AND event_type IN ('view', 'use_online', 'netdisk_click')
             GROUP BY day, event_type",
            [':since' => $since]
        ) as $row) {
            $map[(string) $row['day']][(string) $row['event_type']] = (int) $row['n'];
        }

        $out = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $day = date('Y-m-d', time() - $i * 86400);
            $counts = $map[$day] ?? [];
            $out[] = [
                'date'          => $day,
                'view'          => $counts['view'] ?? 0,
                'use_online'    => $counts['use_online'] ?? 0,
                'netdisk_click' => $counts['netdisk_click'] ?? 0,
            ];
        }

        return $out;
    }
}
