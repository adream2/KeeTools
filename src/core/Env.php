<?php
declare(strict_types=1);

namespace App\Core;

/**
 * .env 读取器
 *
 * 把 .env 解析进内存供全站读取。
 *
 * 为什么不用 putenv()：进程环境变量是全局可变的，在并发请求或测试中
 * 会互相污染，且无法回滚。内存数组则随进程生命周期自然隔离。
 */
final class Env
{
    /** @var array<string, string> 已解析的键值对 */
    private static array $vars = [];

    /** 是否已尝试过加载（避免重复 IO；即使文件不存在也置位） */
    private static bool $loaded = false;

    /**
     * 加载 .env 文件。文件不存在时不报错，允许纯默认值启动。
     */
    public static function load(string $file): void
    {
        if (self::$loaded) {
            return;
        }
        self::$loaded = true;

        if (!is_file($file) || !is_readable($file)) {
            return;
        }

        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }

            // 去掉行尾注释。仅当 # 前有空白才算注释，
            // 这样值里出现的 #（如密码）不会被误截断。
            $commentAt = self::findInlineComment($line);
            if ($commentAt !== null) {
                $line = rtrim(substr($line, 0, $commentAt));
            }

            $eq = strpos($line, '=');
            if ($eq === false) {
                continue;
            }

            $key = trim(substr($line, 0, $eq));
            $value = trim(substr($line, $eq + 1));
            if ($key === '') {
                continue;
            }

            self::$vars[$key] = self::unquote($value);
        }
    }

    /**
     * 读取配置值。
     *
     * 顺序：.env 文件 → 真实环境变量 → 默认值。
     * 保留真实环境变量回落，便于容器部署时不落 .env 文件。
     */
    public static function get(string $key, ?string $default = null): ?string
    {
        if (array_key_exists($key, self::$vars)) {
            return self::$vars[$key];
        }

        $fromEnv = getenv($key);
        if ($fromEnv !== false) {
            return (string) $fromEnv;
        }

        return $default;
    }

    /**
     * 读取布尔值。兼容 1/true/yes/on 四种写法。
     */
    public static function bool(string $key, bool $default = false): bool
    {
        $value = self::get($key);
        if ($value === null || $value === '') {
            return $default;
        }

        return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on'], true);
    }

    /**
     * 读取整数值。
     */
    public static function int(string $key, int $default = 0): int
    {
        $value = self::get($key);
        if ($value === null || trim($value) === '') {
            return $default;
        }

        return (int) $value;
    }

    /**
     * 读取逗号分隔列表，自动去空白并剔除空项。
     *
     * @return list<string>
     */
    public static function list(string $key, string $separator = ','): array
    {
        $value = self::get($key);
        if ($value === null || trim($value) === '') {
            return [];
        }

        $parts = explode($separator, $value);
        $result = [];
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part !== '') {
                $result[] = $part;
            }
        }

        return $result;
    }

    /**
     * @return array<string, string>
     */
    public static function all(): array
    {
        return self::$vars;
    }

    public static function isProduction(): bool
    {
        return self::get('APP_ENV', 'local') === 'production';
    }

    public static function isDebug(): bool
    {
        return self::bool('APP_DEBUG', false);
    }

    /**
     * 仅供测试使用：写入内存变量，不触碰文件。
     */
    public static function set(string $key, string $value): void
    {
        self::$vars[$key] = $value;
    }

    /**
     * 重置为未加载状态（测试用）。
     */
    public static function reset(): void
    {
        self::$vars = [];
        self::$loaded = false;
    }

    /**
     * 定位行内注释起点。只有 # 前面是空白时才算注释。
     */
    private static function findInlineComment(string $line): ?int
    {
        $length = strlen($line);
        for ($i = 1; $i < $length; $i++) {
            if ($line[$i] !== '#') {
                continue;
            }
            $prev = $line[$i - 1];
            if ($prev === ' ' || $prev === "\t") {
                return $i;
            }
        }

        return null;
    }

    /**
     * 去掉包裹值的成对引号。
     */
    private static function unquote(string $value): string
    {
        $length = strlen($value);
        if ($length < 2) {
            return $value;
        }

        $first = $value[0];
        $last = $value[$length - 1];
        if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
            return substr($value, 1, -1);
        }

        return $value;
    }
}
