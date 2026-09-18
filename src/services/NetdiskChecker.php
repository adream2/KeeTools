<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Logger;

/**
 * 网盘链接探活
 *
 * 结论三态：ok / invalid / unknown。
 * 各网盘的「分享失效」页面表现不一（有的仍返回 200），基础版只做
 * HTTP 状态判定：404 / 410 视为失效，2xx 视为存活，其余（超时 /
 * 反爬拦截）视为未知。结果只更新 last_checked_at / check_status，
 * 不自动下架 —— invalid 由站长人工确认后处理。
 */
final class NetdiskChecker
{
    private const TIMEOUT_SECONDS = 10;

    /**
     * 探活单个链接。返回状态字符串。
     */
    public function check(string $url): string
    {
        if (!preg_match('#^https?://#i', $url)) {
            return 'invalid';
        }

        $status = $this->requestStatus($url);

        return match (true) {
            $status >= 200 && $status < 300 => 'ok',
            $status === 404 || $status === 410 => 'invalid',
            default => 'unknown',
        };
    }

    /**
     * 批量探活到期链接，逐条回写结果。返回 [checked, ok, invalid] 统计。
     *
     * @return array{checked: int, ok: int, invalid: int}
     */
    public function checkDue(int $limit = 20): array
    {
        $repository = new NetdiskRepository();
        $stats = ['checked' => 0, 'ok' => 0, 'invalid' => 0];

        foreach ($repository->dueForCheck($limit) as $link) {
            $status = $this->check((string) $link['url']);
            $repository->markChecked((int) $link['id'], $status);
            $stats['checked']++;
            if ($status === 'ok') {
                $stats['ok']++;
            } elseif ($status === 'invalid') {
                $stats['invalid']++;
            }
        }

        return $stats;
    }

    /**
     * HEAD 优先（省流量），失败或 4xx/5xx 时退化为 GET。
     * 不跟随无限重定向；SSL 正常校验（安全规范 §）。
     */
    private function requestStatus(string $url): int
    {
        $status = $this->curlStatus($url, 'HEAD');
        if ($status < 400 && $status > 0) {
            return $status;
        }

        // 很多网盘对 HEAD 返回异常状态，GET 复核一次
        $getStatus = $this->curlStatus($url, 'GET');

        return $getStatus > 0 ? $getStatus : $status;
    }

    private function curlStatus(string $url, string $method): int
    {
        if (!function_exists('curl_init')) {
            return 0;
        }

        $ch = curl_init($url);
        if ($ch === false) {
            return 0;
        }

        curl_setopt_array($ch, [
            CURLOPT_NOBODY         => $method === 'HEAD',
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_TIMEOUT        => self::TIMEOUT_SECONDS,
            CURLOPT_CONNECTTIMEOUT => self::TIMEOUT_SECONDS,
            CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; KeeToolsChecker/1.0)',
            CURLOPT_RETURNTRANSFER => true,
        ]);

        curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($status === 0 && $error !== '') {
            Logger::debug('网盘探活请求失败', ['method' => $method, 'error' => $error]);
        }

        return $status;
    }
}
