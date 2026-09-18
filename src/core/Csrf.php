<?php
declare(strict_types=1);

namespace App\Core;

/**
 * CSRF 防护
 *
 * 所有 POST/PUT/DELETE 必须校验。例外是 /api/track（公开统计端点），
 * 该端点改用具 IP 限流 + 参数白名单防护。
 *
 * 用 hash_equals 而非 === ：字符串比较的短路特性会泄露前缀匹配长度，
 * 使攻击者能逐字节爆破 token。
 */
final class Csrf
{
    private const SESSION_KEY = '_csrf';
    private const FIELD_NAME = '_csrf';
    private const HEADER_NAME = 'X-CSRF-Token';

    /**
     * 取得（必要时生成）当前会话的 token。
     */
    public static function token(): string
    {
        $token = $_SESSION[self::SESSION_KEY] ?? null;

        if (!is_string($token) || $token === '') {
            $token = Security::randomToken(32);
            $_SESSION[self::SESSION_KEY] = $token;
        }

        return $token;
    }

    /**
     * 输出隐藏表单字段。
     */
    public static function field(): string
    {
        return '<input type="hidden" name="' . self::FIELD_NAME . '" value="'
            . Security::escape(self::token()) . '">';
    }

    public static function fieldName(): string
    {
        return self::FIELD_NAME;
    }

    public static function headerName(): string
    {
        return self::HEADER_NAME;
    }

    /**
     * 校验 token。失败即中止请求（419 = 会话过期）。
     *
     * 这里直接 exit 而非抛异常：CSRF 失败意味着请求来源不可信，
     * 不应再走任何后续业务逻辑或错误页渲染。
     */
    public static function verify(?string $token): void
    {
        if (!self::isValid($token)) {
            Logger::warning('CSRF 校验失败', [
                'ip'   => Security::clientIp(),
                'uri'  => $_SERVER['REQUEST_URI'] ?? '',
                'method' => $_SERVER['REQUEST_METHOD'] ?? '',
            ]);

            http_response_code(419);
            header('Content-Type: text/html; charset=UTF-8');
            exit('CSRF 校验失败：页面可能已过期，请刷新后重试。');
        }
    }

    /**
     * 从请求中提取 token（表单字段优先，其次请求头）。
     */
    public static function fromRequest(Request $request): ?string
    {
        $field = $request->post(self::FIELD_NAME);
        if (is_string($field) && $field !== '') {
            return $field;
        }

        $header = $request->header(self::HEADER_NAME);
        if (is_string($header) && $header !== '') {
            return $header;
        }

        return null;
    }

    /**
     * 校验请求中的 token。
     */
    public static function verifyRequest(Request $request): void
    {
        self::verify(self::fromRequest($request));
    }

    /**
     * 纯校验，不中止，供需要自定义处理的场景使用。
     */
    public static function isValid(?string $token): bool
    {
        if (!is_string($token) || $token === '') {
            return false;
        }

        $known = $_SESSION[self::SESSION_KEY] ?? null;
        if (!is_string($known) || $known === '') {
            return false;
        }

        return hash_equals($known, $token);
    }

    /**
     * 轮换 token（登录成功 / 登出时调用）。
     */
    public static function rotate(): void
    {
        $_SESSION[self::SESSION_KEY] = Security::randomToken(32);
    }
}
