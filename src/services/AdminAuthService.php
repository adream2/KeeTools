<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\App;
use App\Core\Database;
use App\Core\Env;
use App\Core\Security;
use RuntimeException;

/**
 * 后台认证（P0 简化模型）
 *
 * 凭据来源是 .env（ADMIN_USERNAME / ADMIN_PASSWORD），而非 admins 表：
 * 单管理员场景下 .env 就是事实源，避免「表里建号」与部署配置两套真相。
 * admins 表留给 P1+ 的多角色（admin / editor）扩展。
 *
 * 登录限流写 login_attempts 表：按用户名与 IP 两个维度计数，
 * 连续失败达到阈值后锁定一段时间，防爆破。
 *
 * 无密码后台：ADMIN_PASSWORDLESS=true 且来源 IP 在白名单内时，
 * 免密登录（仅限本机 / 内网，bootstrap 生产自检会强制要求白名单）。
 */
final class AdminAuthService
{
    private const MAX_ATTEMPTS = 5;

    private const LOCK_MINUTES = 15;

    private ?Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db;
    }

    /**
     * 密码登录是否可用（未配置 ADMIN_PASSWORD 时只剩无密码通道）。
     */
    public static function credentialsConfigured(): bool
    {
        return (Env::get('ADMIN_PASSWORD', '') ?? '') !== '';
    }

    /**
     * 无密码后台是否配置开启。
     */
    public static function passwordlessEnabled(): bool
    {
        return Env::bool('ADMIN_PASSWORDLESS');
    }

    /**
     * 当前请求的 IP 是否允许无密码登录。
     *
     * 白名单必须是具体 IP，'*' 一律不认（bootstrap 生产自检同样拦截）。
     */
    public static function passwordlessAllowed(): bool
    {
        if (!self::passwordlessEnabled()) {
            return false;
        }

        $allowed = Env::list('ADMIN_PASSWORDLESS_IPS');
        if ($allowed === [] || in_array('*', $allowed, true)) {
            return false;
        }

        return in_array(Security::clientIp(), $allowed, true);
    }

    /**
     * 是否已被锁定（该用户名或该 IP 近期失败次数达阈值）。
     */
    public function isLocked(string $username, string $ip): bool
    {
        return $this->failureCount($username, $ip) >= self::MAX_ATTEMPTS;
    }

    /**
     * 校验用户名 + 密码。成功返回 true 并清空失败计数。
     */
    public function attempt(string $username, string $password): bool
    {
        $envUser = (Env::get('ADMIN_USERNAME', 'admin') ?: 'admin');
        $envPassword = (string) (Env::get('ADMIN_PASSWORD', '') ?? '');

        if ($envPassword === '' || $this->isLocked($username, Security::clientIp())) {
            return false;
        }

        $userOk = hash_equals($envUser, $username);
        $passOk = hash_equals($envPassword, $password);

        if (!$userOk || !$passOk) {
            $this->recordFailure($username);
            $this->recordFailure('');

            return false;
        }

        $this->clearFailures($username);

        return true;
    }

    private function failureCount(string $username, string $ip): int
    {
        $cutoff = date('Y-m-d H:i:s', time() - self::LOCK_MINUTES * 60);

        return (int) $this->db()->fetchColumn(
            'SELECT COUNT(*) FROM login_attempts
             WHERE succeeded = 0
               AND attempted_at >= :cutoff
               AND (username = :username OR ip_hash = :ip_hash)',
            [
                ':cutoff'   => $cutoff,
                ':username' => $username,
                ':ip_hash'  => Security::hashIp($ip),
            ]
        );
    }

    private function recordFailure(string $username): void
    {
        $this->db()->execute(
            'INSERT INTO login_attempts (username, ip_hash, attempted_at, succeeded)
             VALUES (:username, :ip_hash, :at, 0)',
            [
                ':username' => $username,
                ':ip_hash'  => Security::hashIp(Security::clientIp()),
                ':at'       => date('Y-m-d H:i:s'),
            ]
        );
    }

    private function clearFailures(string $username): void
    {
        // 成功后只清该用户名的失败记录；IP 维度保留可继续审计
        $this->db()->execute(
            'DELETE FROM login_attempts WHERE username = :username AND succeeded = 0',
            [':username' => $username]
        );
    }

    private function db(): Database
    {
        if ($this->db !== null) {
            return $this->db;
        }
        if (!App::hasDb()) {
            throw new RuntimeException('数据库未初始化');
        }

        return App::db();
    }
}
