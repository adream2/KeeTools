<?php
declare(strict_types=1);

/**
 * 全局模板辅助函数
 *
 * 只放「模板里高频使用、且必须无条件转义」的函数。
 * 之所以不用类静态方法调用，是因为模板里 e($x) 比 Html::escape($x) 更难漏写。
 */

use App\Core\Env;
use App\Core\Security;

if (!function_exists('e')) {
    /**
     * HTML 转义。模板中所有动态输出必须经此函数。
     *
     * 命名刻意取短：越短越不容易被开发者嫌麻烦而绕过。
     */
    function e(mixed $value): string
    {
        if ($value === null || is_bool($value)) {
            return '';
        }

        if (is_scalar($value)) {
            return Security::escape((string) $value);
        }

        // 非标量（数组/对象）不应直接输出，转义其可读形式便于发现误用
        return Security::escape(print_r($value, true));
    }
}

if (!function_exists('asset')) {
    /**
     * 静态资源 URL。
     *
     * 附加基于文件修改时间的版本串：CSS/JS 更新后浏览器立即可见，
     * 无需手动改版本号，也避免用户看到旧样式。
     */
    function asset(string $path): string
    {
        $relative = ltrim($path, '/');
        $url = '/assets/' . $relative;

        $file = dirname(__DIR__, 2) . '/public/assets/' . $relative;
        if (is_file($file)) {
            $url .= '?v=' . filemtime($file);
        }

        return $url;
    }
}

if (!function_exists('url')) {
    /**
     * 站内 URL。统一走此函数，便于将来支持子目录部署。
     */
    function url(string $path = '/'): string
    {
        if ($path === '' || $path === '/') {
            return '/';
        }

        return '/' . ltrim($path, '/');
    }
}

if (!function_exists('icon')) {
    /**
     * 内联 SVG sprite 图标。
     *
     * 用 sprite 而非每处内联完整 SVG：图标重复出现时体积可控。
     * aria-hidden 默认开启，因为图标是装饰性的，语义由邻近文字承担。
     */
    function icon(string $name, string $class = ''): string
    {
        $classes = trim('icon ' . $class);
        $href = asset('icons/sprite.svg') . '#i-' . $name;

        return '<svg class="' . e($classes) . '" aria-hidden="true" focusable="false">'
            . '<use href="' . e($href) . '"></use></svg>';
    }
}

if (!function_exists('site_name')) {
    /**
     * 站点名。优先级：数据库配置 > .env > 默认。
     */
    function site_name(): string
    {
        return (string) (App\Core\Config::get('SITE_NAME') ?? 'EduTools');
    }
}

if (!function_exists('site_description')) {
    function site_description(): string
    {
        return (string) (App\Core\Config::get('SITE_DESCRIPTION') ?? '');
    }
}

if (!function_exists('is_debug')) {
    function is_debug(): bool
    {
        return Env::isDebug();
    }
}
