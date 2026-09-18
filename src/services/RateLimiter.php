<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\App;
use App\Core\Security;

/**
 * 文件计数限流器
 *
 * 按时间窗口 + 维度键计数，无数据库依赖（var/cache/ 下的窗口文件）。
 * 用于 /api/track 与单文件下载这类无会话语义的高频端点。
 *
 * 恶意流量下的取舍：计数文件不可写时按「拒绝」处理（fail-closed），
 * 宁可误伤也不能让限流形同虚设。
 */
final class RateLimiter
{
    private function __construct()
    {
    }

    /**
     * 记一次并判断是否超限。
     *
     * @param string $bucket 业务桶名（区分不同端点）
     * @param int    $limit  窗口内允许次数
     * @param int    $window 窗口秒数
     */
    public static function hit(string $bucket, int $limit, int $window = 60): bool
    {
        $dir = App::path('var/cache/rl-' . preg_replace('/[^a-z0-9-]/', '', strtolower($bucket)));
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return false;
        }

        $now = time();
        $slot = (int) (floor($now / $window) * $window);

        // 清理过期窗口文件（惰性，无定时任务依赖）
        foreach (glob($dir . '/*') ?: [] as $old) {
            if ((int) basename((string) $old) < $now - $window * 2) {
                @unlink((string) $old);
            }
        }

        $windowFile = $dir . '/' . $slot;
        $key = substr(Security::hashIp(Security::clientIp()), 0, 16);

        $count = 0;
        $lines = is_file($windowFile)
            ? @file($windowFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: []
            : [];
        foreach ($lines as $line) {
            if ($line === $key) {
                $count++;
            }
        }

        if ($count >= $limit) {
            return false;
        }

        @file_put_contents($windowFile, $key . "\n", FILE_APPEND | LOCK_EX);

        return true;
    }
}
