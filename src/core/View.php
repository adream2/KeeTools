<?php
declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * 模板渲染
 *
 * 纯 PHP 模板，不引入模板引擎：一是零依赖原则，二是 PHP 本身就是
 * 模板语言，多一层编译只会增加调试成本。
 *
 * 视图通过 include 执行，作用域内变量即模板可用变量；布局用「先渲染
 * 内容再嵌入布局」的两段式，避免 ob_start 嵌套带来的顺序陷阱。
 */
final class View
{
    private static string $viewPath = '';

    /** 布局中可用的共享变量（站点名、当前管理员等） */
    private static array $shared = [];

    /** @var array<int, string> 已渲染的片段栈，用于 section 机制 */
    private static array $sections = [];

    public static function init(string $viewPath): void
    {
        self::$viewPath = $viewPath;
    }

    /**
     * 注册全局共享变量，所有模板可用。
     */
    public static function share(string $key, mixed $value): void
    {
        self::$shared[$key] = $value;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function shareMany(array $data): void
    {
        self::$shared = array_merge(self::$shared, $data);
    }

    /**
     * 渲染模板并套用布局。
     *
     * @param string               $view   相对 views/ 的路径，不含 .php，如 pages/home
     * @param array<string, mixed> $data   模板变量
     * @param string|null          $layout 布局名，null 表示不套布局
     */
    public static function render(string $view, array $data = [], ?string $layout = 'layout/site'): string
    {
        $content = self::renderPartial($view, $data);

        if ($layout === null) {
            return $content;
        }

        // 布局内用 $content 取子模板内容，用 $pageTitle 等取共享变量
        return self::renderPartial($layout, array_merge($data, ['content' => $content]));
    }

    /**
     * 渲染单个模板文件（不含布局）。
     *
     * @param array<string, mixed> $data
     */
    public static function renderPartial(string $view, array $data = []): string
    {
        $file = self::resolve($view);

        // extract 后用 ob 捕获输出。EXTR_SKIP 防止 $data 覆盖 $file 等局部变量。
        extract(array_merge(self::$shared, $data), EXTR_SKIP);

        ob_start();
        try {
            require $file;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }

        $output = ob_get_clean();

        return $output === false ? '' : $output;
    }

    /**
     * 在模板内包含片段（如 partials/header）。
     *
     * @param array<string, mixed> $data
     */
    public static function include(string $view, array $data = []): void
    {
        echo self::renderPartial($view, $data);
    }

    /**
     * 视图片段是否存在（用于可选区域）。
     */
    public static function exists(string $view): bool
    {
        try {
            self::resolve($view);

            return true;
        } catch (RuntimeException) {
            return false;
        }
    }

    /**
     * 解析模板文件的绝对路径，并校验未越出 views 目录。
     *
     * 视图名来自代码而非用户输入，但仍做校验：一旦将来某处把用户输入
     * 误传入视图名，这里能兜住。
     */
    private static function resolve(string $view): string
    {
        // 只允许字母数字、斜杠、连字符、下划线、点
        if (preg_match('#^[a-zA-Z0-9_\-/\.]+$#', $view) !== 1) {
            throw new RuntimeException('非法视图名: ' . $view);
        }

        if (str_contains($view, '..')) {
            throw new RuntimeException('视图名含路径穿越: ' . $view);
        }

        $file = self::$viewPath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $view) . '.php';

        if (!is_file($file)) {
            throw new RuntimeException('视图不存在: ' . $view);
        }

        return $file;
    }
}
