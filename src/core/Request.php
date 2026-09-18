<?php
declare(strict_types=1);

namespace App\Core;

/**
 * HTTP 请求
 *
 * 对超全局变量的一层只读封装：把 $_GET / $_POST / $_SERVER 收在一个对象里，
 * 便于控制器测试（可构造假请求）与统一处理默认值、类型转换。
 */
final class Request
{
    /** 路由匹配出的路径参数，由 Router 注入 */
    private array $attributes = [];

    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $body
     * @param array<string, mixed> $server
     * @param array<string, mixed> $files
     * @param array<string, string> $cookies
     */
    public function __construct(
        private array $query = [],
        private array $body = [],
        private array $server = [],
        private array $files = [],
        private array $cookies = [],
        private ?string $rawBody = null
    ) {
    }

    /**
     * 从超全局变量构造。
     */
    public static function fromGlobals(): self
    {
        $raw = file_get_contents('php://input');

        return new self(
            $_GET,
            $_POST,
            $_SERVER,
            $_FILES,
            $_COOKIE,
            $raw === false ? null : $raw
        );
    }

    public function method(): string
    {
        return strtoupper((string) ($this->server['REQUEST_METHOD'] ?? 'GET'));
    }

    public function isPost(): bool
    {
        return $this->method() === 'POST';
    }

    /**
     * 路径部分（不含查询串），已规范化。
     */
    public function path(): string
    {
        $uri = (string) ($this->server['REQUEST_URI'] ?? '/');
        $path = parse_url($uri, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            return '/';
        }

        // 折叠重复斜杠并去掉末尾斜杠（根路径除外），
        // 否则 /tool/abc/ 与 /tool/abc 会被视为不同路由
        $path = preg_replace('#/+#', '/', $path) ?? $path;
        if ($path !== '/' ) {
            $path = rtrim($path, '/');
            if ($path === '') {
                $path = '/';
            }
        }

        return $path;
    }

    public function query(string $key, ?string $default = null): ?string
    {
        $value = $this->query[$key] ?? null;

        return is_scalar($value) ? (string) $value : $default;
    }

    /**
     * @return array<string, mixed>
     */
    public function allQuery(): array
    {
        return $this->query;
    }

    public function post(string $key, ?string $default = null): ?string
    {
        $value = $this->body[$key] ?? null;

        return is_scalar($value) ? (string) $value : $default;
    }

    /**
     * @return array<string, mixed>
     */
    public function allPost(): array
    {
        return $this->body;
    }

    /**
     * 读取输入（POST 字段或查询串），POST 优先。
     */
    public function input(string $key, ?string $default = null): ?string
    {
        if (array_key_exists($key, $this->body)) {
            return $this->post($key, $default);
        }

        return $this->query($key, $default);
    }

    public function int(string $key, int $default = 0): int
    {
        $value = $this->input($key);
        if ($value === null || trim($value) === '') {
            return $default;
        }

        return (int) $value;
    }

    public function bool(string $key, bool $default = false): bool
    {
        $value = $this->input($key);
        if ($value === null || trim($value) === '') {
            return $default;
        }

        return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on'], true);
    }

    /**
     * JSON 请求体解析结果。非 JSON 或解析失败返回空数组。
     *
     * @return array<string, mixed>
     */
    public function json(): array
    {
        $raw = $this->rawBody();
        if ($raw === null || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    public function rawBody(): ?string
    {
        return $this->rawBody;
    }

    /**
     * 读取请求头（大小写不敏感）。
     */
    public function header(string $name): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));

        // Content-Type / Content-Length 在 $_SERVER 中无 HTTP_ 前缀
        if (!isset($this->server[$key])) {
            $fallback = strtoupper(str_replace('-', '_', $name));
            if (isset($this->server[$fallback])) {
                return (string) $this->server[$fallback];
            }

            return null;
        }

        return (string) $this->server[$key];
    }

    public function cookie(string $name, ?string $default = null): ?string
    {
        $value = $this->cookies[$name] ?? null;

        return is_scalar($value) ? (string) $value : $default;
    }

    public function ip(): string
    {
        $remote = (string) ($this->server['REMOTE_ADDR'] ?? '');

        // 复用 Security 的可信代理判定，避免两处逻辑不一致
        $trusted = ['127.0.0.1', '::1'];
        if (in_array($remote, $trusted, true)
            && isset($this->server['HTTP_X_FORWARDED_FOR'])
        ) {
            $parts = explode(',', (string) $this->server['HTTP_X_FORWARDED_FOR']);
            $first = trim($parts[0]);
            if ($first !== '') {
                return $first;
            }
        }

        return $remote;
    }

    public function userAgent(): string
    {
        return (string) ($this->server['HTTP_USER_AGENT'] ?? '');
    }

    public function referer(): string
    {
        return (string) ($this->server['HTTP_REFERER'] ?? '');
    }

    public function isAjax(): bool
    {
        $requestedWith = $this->header('X-Requested-With');

        return $requestedWith !== null && strtolower($requestedWith) === 'xmlhttprequest';
    }

    /**
     * 是否期望 JSON 响应。控制器据此决定渲染 HTML 还是返回 JSON。
     */
    public function wantsJson(): bool
    {
        $accept = $this->header('Accept') ?? '';
        if (str_contains($accept, 'application/json')) {
            return true;
        }

        $contentType = $this->header('Content-Type') ?? '';

        return str_contains($contentType, 'application/json') || $this->isAjax();
    }

    public function setAttribute(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }

    public function attribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    /**
     * @return array<string, mixed>
     */
    public function attributes(): array
    {
        return $this->attributes;
    }
}
