<?php
declare(strict_types=1);

namespace App\Core;

/**
 * 会话管理
 *
 * 前台也开启会话（详情页需要写 CSRF token），故在 bootstrap 阶段统一启动。
 *
 * 安全要点（对应 安全规范.md §6）：
 * - Cookie 名不用默认 PHPSESSID，避免暴露技术栈
 * - HttpOnly 阻止 JS 读取，SameSite=Lax 缓解 CSRF
 * - 生产环境加 Secure，防明文链路窃取
 * - 登录成功后重新生成会话 ID，防会话固定攻击
 */
final class Session
{
    /** 上次活动时间戳在会话中的键 */
    private const LAST_ACTIVITY = '_last_activity';

    private static bool $started = false;

    /**
     * 启动会话并施加空闲超时。
     *
     * @param int $lifetime 空闲超时秒数，0 表示不超时
     */
    public static function start(int $lifetime = 7200): void
    {
        if (self::$started || session_status() === PHP_SESSION_ACTIVE) {
            self::$started = true;
            return;
        }

        $name = Env::get('SESSION_NAME', 'et_session') ?: 'et_session';

        session_name($name);
        session_set_cookie_params([
            // lifetime 0 = 浏览器关闭即失效，实际超时由下面的空闲判定控制
            'lifetime' => 0,
            'path'     => '/',
            'domain'   => '',
            // 必须 true：否则 XSS 可读走会话 ID，绕过 HttpOnly 意义
            'httponly' => true,
            'samesite' => 'Lax',
            'secure'   => Env::isProduction(),
        ]);

        session_start();
        self::$started = true;

        self::enforceTimeout($lifetime);
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public static function remove(string $key): void
    {
        unset($_SESSION[$key]);
    }

    /**
     * 一次性读取（读取即删除），用于 flash 消息。
     */
    public static function pull(string $key, mixed $default = null): mixed
    {
        $value = self::get($key, $default);
        self::remove($key);

        return $value;
    }

    /**
     * 设置闪存消息，下次请求读取一次后自动消失。
     */
    public static function flash(string $type, string $message): void
    {
        $_SESSION['_flash'][$type] = $message;
    }

    /**
     * @return array<string, string>
     */
    public static function pullFlash(): array
    {
        $flash = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);

        return is_array($flash) ? $flash : [];
    }

    /**
     * 重新生成会话 ID。登录成功、权限变更后必须调用。
     */
    public static function regenerate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            // true = 删除旧会话文件，防止旧 ID 仍可用
            session_regenerate_id(true);
        }
    }

    /**
     * 彻底销毁会话（登出）。
     */
    public static function destroy(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        $_SESSION = [];

        // 过期 Cookie 才能真正让浏览器删除它
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name() ?: 'et_session',
                '',
                [
                    'expires'  => time() - 42000,
                    'path'     => $params['path'],
                    'domain'   => $params['domain'],
                    'secure'   => $params['secure'],
                    'httponly' => $params['httponly'],
                    'samesite' => $params['samesite'] ?? 'Lax',
                ]
            );
        }

        session_destroy();
        self::$started = false;
    }

    public static function isActive(): bool
    {
        return session_status() === PHP_SESSION_ACTIVE;
    }

    /**
     * 空闲超时判定。超时即销毁会话。
     *
     * 注意：这里只清 $_SESSION 而不动 Cookie，因为 Cookie 生命周期由浏览器控制，
     * 服务端只负责让数据失效。
     */
    private static function enforceTimeout(int $lifetime): void
    {
        if ($lifetime <= 0) {
            return;
        }

        $now = time();
        $last = $_SESSION[self::LAST_ACTIVITY] ?? null;

        if (is_int($last) && ($now - $last) > $lifetime) {
            $_SESSION = [];
            session_regenerate_id(true);
            $_SESSION[self::LAST_ACTIVITY] = $now;

            return;
        }

        $_SESSION[self::LAST_ACTIVITY] = $now;
    }
}
