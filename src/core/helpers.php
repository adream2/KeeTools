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
     *
     * $versioned = false 用于 SVG sprite：`<use>` 的外部引用在教室常见的
     * Win7 + 老版 Chrome / 360 内核上对「查询串 + 片段标识」支持不稳，
     * 故 sprite 走不带查询串的稳定地址（见 icon()）。
     */
    function asset(string $path, bool $versioned = true): string
    {
        $relative = ltrim($path, '/');
        $url = '/assets/' . $relative;

        if (!$versioned) {
            return $url;
        }

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
     *
     * 同时输出 href 与 xlink:href：教室常见 Win7 + 老版 Chrome / 360，
     * 仅写 href 在这些内核上不会渲染。图标缺失时不报错（只是不显示）。
     */
    function icon(string $name, string $class = ''): string
    {
        $classes = trim('icon ' . $class);
        $href = e(asset('icons/sprite.svg', false) . '#i-' . $name);

        return '<svg class="' . e($classes) . '" aria-hidden="true" focusable="false">'
            . '<use href="' . $href . '" xlink:href="' . $href . '"></use></svg>';
    }
}

if (!function_exists('site_name')) {
    /**
     * 站点名。优先级：数据库配置 > .env > 默认。
     */
    function site_name(): string
    {
        return (string) (App\Core\Config::get('SITE_NAME') ?? 'KeeTools');
    }
}

if (!function_exists('site_description')) {
    function site_description(): string
    {
        return (string) (App\Core\Config::get('SITE_DESCRIPTION') ?? '');
    }
}

if (!function_exists('site_url')) {
    /**
     * 站点绝对地址。
     *
     * 用于下载文件内的品牌回流链接（file:// 环境必须绝对地址）。
     * 以当前请求 Host 优先（生产域名切换零配置），请求上下文缺失时
     * 回退 .env 的 APP_URL，最后兜底相对根。
     */
    function site_url(): string
    {
        $host = $_SERVER['HTTP_HOST'] ?? '';
        if ($host !== '') {
            $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || (($_SERVER['SERVER_PORT'] ?? '') === '443')
                || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

            return ($https ? 'https://' : 'http://') . $host;
        }

        $appUrl = trim((string) \App\Core\Env::get('APP_URL', ''));
        if ($appUrl !== '') {
            return rtrim($appUrl, '/');
        }

        return '';
    }
}

if (!function_exists('is_debug')) {
    function is_debug(): bool
    {
        return Env::isDebug();
    }
}

if (!function_exists('page_not_found')) {
    /**
     * 渲染 404 错误页（内容缺失时控制器用，与路由级 404 视觉一致）。
     */
    function page_not_found(string $message = ''): \App\Core\Response
    {
        $exception = new RuntimeException($message !== '' ? $message : 'NOT_FOUND');

        $html = \App\Core\View::render('pages/error', [
            'status'     => 404,
            'message'    => $message,
            'exception'  => $exception,
            'showDetail' => is_debug(),
        ]);

        return \App\Core\Response::html($html, 404);
    }
}

if (!function_exists('tool_type_label')) {
    /**
     * 工具类型展示名。
     */
    function tool_type_label(?string $type): string
    {
        return match ($type) {
            'fixed'      => '知识速查',
            'experiment' => '实验模拟',
            default      => '课堂互动',
        };
    }
}

if (!function_exists('tool_type_icon')) {
    /**
     * 工具类型对应的 sprite 图标名（须已在 icons.txt 登记）。
     */
    function tool_type_icon(?string $type): string
    {
        return match ($type) {
            'fixed'      => 'book-open',
            'experiment' => 'flask-conical',
            default      => 'play',
        };
    }
}

if (!function_exists('tool_type_tag_class')) {
    /**
     * 工具类型对应的 tag 配色类。
     */
    function tool_type_tag_class(?string $type): string
    {
        return match ($type) {
            'fixed'      => 'tag-success',
            'experiment' => 'tag-warning',
            default      => 'tag-info',
        };
    }
}

if (!function_exists('tool_requires_items')) {
    /**
     * 工具运行环境要求（manifest `requires`）→ 详情页「使用要求」条目。
     *
     * 工具只声明能力 key；"为什么可能用不了 / 怎么解决"的标准措辞集中在此维护，
     * 改一次全站生效（manifest 规范 §3.6.2）。未登记的 key 直接忽略。
     *
     * @param mixed $requires manifest requires 字段（字符串数组）
     * @return list<array{key: string, label: string, desc: string, icon: string}>
     */
    function tool_requires_items(mixed $requires): array
    {
        if (!is_array($requires)) {
            return [];
        }

        $catalog = [
            'microphone' => [
                'label' => '需要麦克风',
                'desc'  => '本工具通过麦克风采集声音，首次使用浏览器会询问权限，请选择「允许」。'
                    . '取麦需要安全上下文：https 与 localhost 稳定可用，http 页面会被浏览器直接禁止；'
                    . '直接双击下载到本地的文件（file://）时能否取麦取决于浏览器安全策略'
                    . '（Safari 等会直接拒绝，这不是工具故障）。若被拒绝，请在本页「在线体验」中打开，'
                    . '或把工具放到本地服务器 / 校园网 HTTPS 环境使用。',
                'icon'  => 'activity',
            ],
            'camera' => [
                'label' => '需要摄像头',
                'desc'  => '本工具通过摄像头采集画面，首次使用浏览器会询问权限，请选择「允许」。'
                    . '取用摄像头同样需要安全上下文（https / localhost 稳定可用，http 会被禁止），'
                    . '本地文件方式打开时能否使用取决于浏览器安全策略。',
                'icon'  => 'eye',
            ],
        ];

        $items = [];
        foreach ($requires as $key) {
            if (is_string($key) && isset($catalog[$key])) {
                $items[] = ['key' => $key] + $catalog[$key];
            }
        }

        return $items;
    }
}

if (!function_exists('grade_range_label')) {
    /**
     * 学段数组 → 人类可读文案（如「全学段」「小学 · 初中」）。
     *
     * @param list<string> $grades
     */
    function grade_range_label(array $grades): string
    {
        if ($grades === []) {
            return '通用';
        }

        $names = [
            '1-2'   => '小学低年级',
            '3-4'   => '小学中年级',
            '5-6'   => '小学高年级',
            '1-6'   => '小学',
            '7-9'   => '初中',
            '10-12' => '高中',
            '1-12'  => '全学段',
        ];

        if (in_array('1-12', $grades, true)) {
            return '全学段';
        }

        $labels = [];
        foreach ($grades as $grade) {
            $labels[] = $names[$grade] ?? $grade;
        }

        return implode(' · ', array_unique($labels));
    }
}

