<?php
declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * 安全辅助
 *
 * 集中放置路径校验、输出转义、IP 取值、哈希等跨模块安全工具。
 * 这些函数散落在各处极易漏用，集中一处便于审计。
 */
final class Security
{
    /** 工具 id 格式：小写字母、数字、连字符，1~64 位 */
    public const TOOL_ID_PATTERN = '/^[a-z0-9-]{1,64}$/';

    /** 可信代理列表。只有直连 IP 在此列表中，才采信 X-Forwarded-For */
    private static array $trustedProxies = ['127.0.0.1', '::1'];

    /**
     * 安全拼接路径并校验未越界。
     *
     * 这是防路径穿越的唯一入口。任何来自用户输入的路径片段都必须走这里，
     * 因为「拼接字符串 + 检查」的写法极易漏掉 ../、绝对路径、软链等绕过方式。
     *
     * @param string $base      允许的根目录（绝对路径）
     * @param string ...$parts  用户可控的路径片段
     * @throws RuntimeException 越界或片段非法时抛出
     */
    public static function safePath(string $base, string ...$parts): string
    {
        $baseReal = realpath($base);
        if ($baseReal === false) {
            throw new RuntimeException('基础目录不存在: ' . $base);
        }

        foreach ($parts as $part) {
            if ($part === '' || $part === '.' || $part === '..') {
                throw new RuntimeException('非法路径片段');
            }
            // 含斜杠即可改变层级；含 NUL 可截断底层系统调用
            if (strpbrk($part, "/\\\0") !== false) {
                throw new RuntimeException('路径片段含非法字符');
            }
        }

        $path = $baseReal . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $parts);
        $real = realpath($path);

        if ($real === false) {
            // 目标尚不存在（如新建文件）时退而校验父目录，
            // 否则首次创建会被误判为越界。
            $parentReal = realpath(dirname($path));
            if ($parentReal === false || !self::isInside($parentReal, $baseReal)) {
                throw new RuntimeException('路径越界');
            }

            return $path;
        }

        if (!self::isInside($real, $baseReal)) {
            throw new RuntimeException('路径越界');
        }

        return $real;
    }

    /**
     * 判断路径是否位于基准目录内（realpath 后的字符串前缀比较）。
     */
    private static function isInside(string $path, string $base): bool
    {
        if ($path === $base) {
            return true;
        }

        return str_starts_with($path, rtrim($base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR);
    }

    /**
     * 校验工具 id 格式。路径校验之外的第二道防线：
     * 格式合法即天然排除 ../、绝对路径等注入形态。
     */
    public static function isValidToolId(string $toolId): bool
    {
        return preg_match(self::TOOL_ID_PATTERN, $toolId) === 1;
    }

    /**
     * 校验工具 id，非法即抛异常。
     *
     * @throws RuntimeException
     */
    public static function assertToolId(string $toolId): string
    {
        if (!self::isValidToolId($toolId)) {
            throw new RuntimeException('非法工具 id');
        }

        return $toolId;
    }

    /**
     * HTML 转义。
     *
     * ENT_QUOTES 保证属性上下文（单双引号）安全；
     * ENT_SUBSTITUTE 保证非法 UTF-8 序列被替换而非返回空串。
     */
    public static function escape(?string $value): string
    {
        return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * 客户端真实 IP。
     *
     * 只有直连 IP 属于可信代理时才采信 X-Forwarded-For ——
     * 否则任何人伪造该头即可绕过 IP 白名单。
     */
    public static function clientIp(): string
    {
        $remote = $_SERVER['REMOTE_ADDR'] ?? '';

        if (in_array($remote, self::$trustedProxies, true)
            && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])
        ) {
            $parts = explode(',', (string) $_SERVER['HTTP_X_FORWARDED_FOR']);
            $first = trim($parts[0]);
            if ($first !== '') {
                return $first;
            }
        }

        return $remote;
    }

    /**
     * 覆盖可信代理列表（反向代理部署时由 bootstrap 注入）。
     *
     * @param list<string> $proxies
     */
    public static function setTrustedProxies(array $proxies): void
    {
        self::$trustedProxies = $proxies;
    }

    /**
     * 带日盐的 IP 哈希。
     *
     * 日盐使哈希每天变化，既可用于当日去重，又无法长期追踪同一用户，
     * 兼顾统计需求与隐私。盐取 APP_KEY + 当日日期，不可逆推。
     */
    public static function hashIp(string $ip): string
    {
        $key = Env::get('APP_KEY', '');
        $salt = $key . date('Y-m-d');

        return hash('sha256', $ip . $salt);
    }

    /**
     * 哈希任意标识（UA / referer 等），同上策略。
     */
    public static function hashValue(string $value): string
    {
        return hash('sha256', $value . Env::get('APP_KEY', ''));
    }

    /**
     * 常量时间比较，防止时序侧信道。
     */
    public static function equals(string $known, string $userProvided): bool
    {
        return hash_equals($known, $userProvided);
    }

    /**
     * 生成随机令牌（十六进制）。
     */
    public static function randomToken(int $bytes = 32): string
    {
        return bin2hex(random_bytes($bytes));
    }

    /**
     * 外部 URL 白名单（docs/站点运营模块设计.md §一.4）。
     *
     * 只放行 http(s)://、mailto:、站内 / 开头路径；
     * 拒绝 javascript: / data: / vbscript: / file: 与协议相对 //。
     * 友链、公告链接、备案链接、二维码等所有运营输入必须过此函数。
     *
     * @return string|null 合法返回原值（trim 后），非法返回 null
     */
    public static function safeExternalUrl(?string $url): ?string
    {
        $url = trim((string) $url);
        if ($url === '') {
            return null;
        }

        // 控制字符与首部空白 tricks（java\tscript: 等）直接拒绝
        if (preg_match('/[\x00-\x20\x7f]/', $url) === 1) {
            return null;
        }

        if (str_starts_with($url, '//')) {
            return null; // 协议相对
        }

        if (str_starts_with($url, '/')) {
            return $url; // 站内路径
        }

        if (preg_match('#^https?://#i', $url) === 1) {
            return $url;
        }

        if (preg_match('#^mailto:[^\s@]+@[^\s@]+$#i', $url) === 1) {
            return $url;
        }

        return null;
    }

    /**
     * 富文本白名单过滤。
     *
     * 工具描述等若允许富文本，必须过滤而非信任。手写实现以遵守零依赖原则，
     * 只放行安全标签，并校验 href 协议防 javascript: 注入。
     */
    public static function sanitizeHtml(string $html): string
    {
        // 先移除危险整块（含内容），这些标签即便属性被清空也不该保留
        $blocked = ['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button'];
        foreach ($blocked as $tag) {
            $html = preg_replace(
                '#<' . $tag . '\b[^>]*>.*?</' . $tag . '\s*>#is',
                '',
                $html
            ) ?? $html;
            // 自闭合或未闭合形态
            $html = preg_replace('#</?' . $tag . '\b[^>]*>#is', '', $html) ?? $html;
        }

        // 移除事件处理器属性（onclick 等）与 javascript: 协议
        $html = preg_replace('#\son\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)#is', '', $html) ?? $html;
        $html = preg_replace('#(href|src)\s*=\s*("|\')?\s*javascript:[^"\'>\s]*#is', '', $html) ?? $html;

        // 只保留白名单标签，其余标签剥离但保留其文字内容
        $allowed = ['p', 'br', 'strong', 'em', 'ul', 'ol', 'li', 'a'];
        $pattern = '#</?([a-z0-9]+)(\s[^>]*)?/?>#i';

        return preg_replace_callback($pattern, static function (array $m) use ($allowed): string {
            $tag = strtolower($m[1]);
            if (!in_array($tag, $allowed, true)) {
                return '';
            }

            // 闭合标签无属性
            if (str_starts_with($m[0], '</')) {
                return '</' . $tag . '>';
            }

            if ($tag === 'br') {
                return '<br>';
            }

            if ($tag === 'a') {
                $href = '';
                if (isset($m[2]) && preg_match('#href\s*=\s*("([^"]*)"|\'([^\']*)\'|([^\s>]+))#i', $m[2], $h)) {
                    $candidate = $h[2] !== '' ? $h[2] : ($h[3] !== '' ? $h[3] : ($h[4] ?? ''));
                    // 只允许 http/https/相对路径/锚点，阻断 javascript: data: 等
                    if (preg_match('#^(https?://|/|\#|\./|\.\./)#i', $candidate) === 1) {
                        $href = ' href="' . self::escape($candidate) . '"';
                    }
                }

                return '<a' . $href . ' rel="noopener noreferrer" target="_blank">';
            }

            return '<' . $tag . '>';
        }, $html) ?? $html;
    }
}
