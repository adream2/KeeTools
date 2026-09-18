<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\App;
use App\Core\Env;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Security;
use RuntimeException;
use Throwable;

/**
 * 统计服务 —— 全站唯一的统计写入出口
 *
 * 三条铁律（docs/需求文档-v2.md §10）：
 *   1. 只保五个事件：view / use_online / netdisk_click / download_direct / sponsor_click。
 *      工具内部任何操作绝不统计。
 *   2. 请求内只写 append-only 缓冲（JSONL 文件），由 scripts/flush_stats.php
 *      每分钟批量落库 → N 次高频小写合并为 1 次事务写，规避 SQLite 写锁竞争。
 *   3. IP 只存哈希（日盐），UA / referer 只存哈希，不落明文。
 *
 * 落库时机：CLI 定时任务 + 后台看板渲染前（保证看板数据新鲜）。
 * 缓冲文件过大时惰性触发，避免纯靠 cron 的部署忘记配置导致缓冲无限增长。
 * 明确不做补偿逻辑：flush 失败的数据随缓冲保留、被限流的事件直接丢弃。
 */
final class StatsService
{
    /** 事件白名单（与 stats 表 CHECK 约束一致） */
    public const EVENTS = ['view', 'use_online', 'use_offline', 'netdisk_click', 'download_direct', 'sponsor_click'];

    /** 缓冲文件超过此大小（字节）时请求内惰性落库 */
    private const BUFFER_FLUSH_SIZE = 524288; // 512KB

    /** /api/track 单 IP 每分钟上限 */
    private const RATE_LIMIT_PER_MINUTE = 60;

    /** use_online 去重窗口（秒）：服务端直记 + 注入脚本上报，同一 IP 同工具只计一次 */
    private const DEDUP_WINDOW_SECONDS = 300;

    private function __construct()
    {
    }

    /**
     * 服务端直记入口（详情页 view、使用页 use_online、中间页 netdisk_click、下载 download_direct）。
     *
     * 统计是次要功能：任何失败（缓冲不可写、库不可用）都静默降级，绝不影响主流程。
     */
    public static function record(string $event, string $toolId = '', ?string $netdiskType = null): void
    {
        if (!in_array($event, self::EVENTS, true)) {
            return;
        }

        self::maybeLazyFlush();
        self::buffer([
            'tool_id'      => $toolId,
            'event_type'   => $event,
            'netdisk_type' => $netdiskType,
            'ip_hash'      => Security::hashIp(Security::clientIp()),
            'ua_hash'      => Security::hashValue(substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 256)),
            'referer'      => self::safeReferer(),
            'created_at'   => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * /api/track 端点入口（前台 JS 上报：注入脚本 use_online、赞助页 sponsor_click）。
     *
     * 防线：参数白名单 → 限流 → use_online 去重。失败返回 false，控制器转 202 静默。
     *
     * @param array<string, mixed> $payload
     */
    public static function trackFromRequest(array $payload): bool
    {
        $event = isset($payload['event']) && is_string($payload['event']) ? $payload['event'] : '';
        if (!in_array($event, self::EVENTS, true)) {
            return false;
        }

        if (!self::checkRateLimit()) {
            return false;
        }

        $toolId = isset($payload['tool']) && is_string($payload['tool'])
            ? substr(preg_replace('/[^a-z0-9-]/', '', strtolower($payload['tool'])) ?? '', 0, 64)
            : '';

        // 注入脚本与服务端直记双通道，同一 IP 同工具同窗口只计一次
        if ($event === 'use_online' && $toolId !== '' && self::recentlyCounted($toolId, $event)) {
            return true;
        }

        $netdiskType = isset($payload['netdisk']) && is_string($payload['netdisk'])
            ? substr($payload['netdisk'], 0, 20)
            : null;

        self::buffer([
            'tool_id'      => $toolId,
            'event_type'   => $event,
            'netdisk_type' => $netdiskType,
            'ip_hash'      => Security::hashIp(Security::clientIp()),
            'ua_hash'      => Security::hashValue(substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 256)),
            'referer'      => self::safeReferer(),
            'created_at'   => date('Y-m-d H:i:s'),
        ]);

        return true;
    }

    /**
     * 缓冲批量落库。返回落库条数。
     *
     * 原子性：先把缓冲 rename 为 .flushing（同一时刻只有一个 flusher 持有），
     * 再逐行入库。中途失败把残余行写回缓冲头部，不丢已缓冲数据。
     */
    public static function flush(): int
    {
        $buffer = self::bufferPath();
        $working = $buffer . '.flushing';

        if (!is_file($buffer)) {
            return 0;
        }

        // rename 在 Windows 上目标存在会失败，先清理残留
        if (is_file($working)) {
            @unlink($working);
        }
        if (!@rename($buffer, $working)) {
            // 并发 flusher 已抢到，本此直接退出
            return 0;
        }

        $raw = @file_get_contents($working);
        if ($raw === false || trim($raw) === '') {
            @unlink($working);

            return 0;
        }

        $rows = [];
        $bad = [];
        foreach (explode("\n", $raw) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $decoded = json_decode($line, true);
            if (is_array($decoded)
                && isset($decoded['event_type'])
                && in_array($decoded['event_type'], self::EVENTS, true)
            ) {
                $rows[] = $decoded;
            } else {
                $bad[] = $line; // 坏行原样保留，不静默吞掉
            }
        }

        $count = 0;
        if ($rows !== []) {
            try {
                $count = self::insertRows($rows);
            } catch (Throwable $e) {
                Logger::warning('统计批量落库失败', ['error' => $e->getMessage()]);
                // 库不可用：数据退回缓冲，等下一轮
                self::appendLines(array_merge($bad, array_map('json_encode', $rows)));
                @unlink($working);

                return 0;
            }
        }

        if ($bad !== []) {
            self::appendLines($bad);
        }
        @unlink($working);

        return $count;
    }

    /**
     * 去重查询：同 IP 同工具同事件在窗口内是否已计数。
     */
    private static function recentlyCounted(string $toolId, string $event): bool
    {
        if (!App::hasStatsDb()) {
            return false;
        }

        try {
            $n = (int) App::statsDb()->fetchColumn(
                'SELECT COUNT(*) FROM stats
                 WHERE tool_id = :tool AND event_type = :event AND ip_hash = :ip
                   AND created_at >= :since',
                [
                    ':tool'  => $toolId,
                    ':event' => $event,
                    ':ip'    => Security::hashIp(Security::clientIp()),
                    ':since' => date('Y-m-d H:i:s', time() - self::DEDUP_WINDOW_SECONDS),
                ]
            );

            return $n > 0;
        } catch (Throwable $e) {
            return false; // 查询失败放行：宁可多计不可漏计
        }
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    private static function insertRows(array $rows): int
    {
        if (!App::hasStatsDb()) {
            throw new RuntimeException('统计库未初始化');
        }

        return App::statsDb()->transaction(function ($db) use ($rows): int {
            $stmt = $db->pdo()->prepare(
                'INSERT INTO stats (tool_id, event_type, netdisk_type, ip_hash, ua_hash, referer, created_at)
                 VALUES (:tool_id, :event_type, :netdisk_type, :ip_hash, :ua_hash, :referer, :created_at)'
            );
            $count = 0;
            foreach ($rows as $row) {
                $stmt->execute([
                    ':tool_id'      => (string) ($row['tool_id'] ?? ''),
                    ':event_type'   => (string) $row['event_type'],
                    ':netdisk_type' => $row['netdisk_type'] ?? null,
                    ':ip_hash'      => $row['ip_hash'] ?? null,
                    ':ua_hash'      => $row['ua_hash'] ?? null,
                    ':referer'      => $row['referer'] ?? null,
                    ':created_at'   => $row['created_at'] ?? date('Y-m-d H:i:s'),
                ]);
                $count++;
            }

            return $count;
        });
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function buffer(array $row): void
    {
        $line = json_encode($row, JSON_UNESCAPED_UNICODE);
        if ($line === false) {
            return;
        }

        $dir = dirname(self::bufferPath());
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return; // 缓冲目录不可写：丢弃（统计允许丢失）
        }

        @file_put_contents(self::bufferPath(), $line . "\n", FILE_APPEND | LOCK_EX);
    }

    /**
     * @param list<string> $lines
     */
    private static function appendLines(array $lines): void
    {
        $dir = dirname(self::bufferPath());
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return;
        }
        @file_put_contents(self::bufferPath(), implode("\n", $lines) . "\n", FILE_APPEND | LOCK_EX);
    }

    /**
     * 惰性落库：缓冲超过阈值时在请求内直接 flush。
     * 保证就算 cron 未配置，缓冲也不会无限增长。
     */
    private static function maybeLazyFlush(): void
    {
        $buffer = self::bufferPath();
        if (is_file($buffer) && filesize($buffer) > self::BUFFER_FLUSH_SIZE) {
            self::flush();
        }
    }

    /**
     * IP 限流：每分钟每 IP 最多 RATE_LIMIT_PER_MINUTE 次。
     */
    private static function checkRateLimit(): bool
    {
        return RateLimiter::hit('track', self::RATE_LIMIT_PER_MINUTE, 60);
    }

    /**
     * referer 只保留站内路径（外站来源不落库），并截断。
     */
    private static function safeReferer(): ?string
    {
        $ref = $_SERVER['HTTP_REFERER'] ?? '';
        if ($ref === '') {
            return null;
        }

        $host = parse_url($ref, PHP_URL_HOST);
        $own = parse_url((string) Env::get('APP_URL', ''), PHP_URL_HOST);

        if ($host !== null && $own !== null && strcasecmp($host, $own) !== 0) {
            return 'external';
        }

        return mb_substr((string) (parse_url($ref, PHP_URL_PATH) ?? ''), 0, 200) ?: null;
    }

    private static function bufferPath(): string
    {
        return App::path('var/cache/stats-buffer.jsonl');
    }
}
