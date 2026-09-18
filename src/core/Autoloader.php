<?php
declare(strict_types=1);

namespace App\Core;

/**
 * 自动加载器（PSR-4 子集）
 *
 * 前缀 App\ 映射到 src/。不引入 Composer：本项目零依赖，
 * 引一个 40 行的自动加载器足以，避免 vendor/ 与构建步骤。
 */
final class Autoloader
{
    /** 命名空间前缀 → 目录，末尾带分隔符 */
    private static array $prefixes = [];

    public static function register(string $prefix, string $baseDir): void
    {
        $prefix = trim($prefix, '\\') . '\\';
        self::$prefixes[$prefix] = rtrim($baseDir, '/\\') . DIRECTORY_SEPARATOR;

        if (count(self::$prefixes) === 1) {
            spl_autoload_register([self::class, 'load']);
        }
    }

    /**
     * 按 PSR-4 规则定位文件。找不到时静默返回，
     * 交由 PHP 抛出「类不存在」错误，避免掩盖真正的问题。
     *
     * 逐段做**大小写不敏感**的目录解析：本项目目录名遵循文档约定为小写
     * （`src/core/`、`src/services/`），而命名空间是 `App\Core\`、`App\Services\`。
     * Windows 文件系统大小写不敏感故直接拼接即可，但部署到 Linux 会因
     * `src/Core/` 不存在而全站类加载失败。此处逐段匹配可同时兼容两者。
     */
    public static function load(string $class): void
    {
        foreach (self::$prefixes as $prefix => $baseDir) {
            if (!str_starts_with($class, $prefix)) {
                continue;
            }

            $relative = substr($class, strlen($prefix));
            $segments = explode('\\', $relative);
            $filename = array_pop($segments) . '.php';

            if ($filename === '.php') {
                continue;
            }

            $dir = $baseDir;
            $resolved = true;

            foreach ($segments as $segment) {
                $next = self::resolveSegment($dir, $segment);
                if ($next === null) {
                    $resolved = false;
                    break;
                }
                $dir = $next;
            }

            if (!$resolved) {
                continue;
            }

            $file = $dir . $filename;
            if (is_file($file)) {
                require $file;

                return;
            }
        }
    }

    /**
     * 在 $dir 下查找名为 $segment 的子目录，忽略大小写。
     *
     * 命中直接拼接的路径时走快路径，避免每次加载都扫目录。
     *
     * @return string|null 带尾部分隔符的目录路径；未找到返回 null
     */
    private static function resolveSegment(string $dir, string $segment): ?string
    {
        $direct = $dir . $segment;
        if (is_dir($direct)) {
            return $direct . DIRECTORY_SEPARATOR;
        }

        $items = @scandir($dir);
        if ($items === false) {
            return null;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            if (strcasecmp($item, $segment) === 0 && is_dir($dir . $item)) {
                return $dir . $item . DIRECTORY_SEPARATOR;
            }
        }

        return null;
    }
}
