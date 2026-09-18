<?php
declare(strict_types=1);

namespace App\Services;

/**
 * 品牌回流注入器（docs/需求文档-v2.md §11.4，2026-09-18 修订）
 *
 * 单文件下载时由服务端强制植入（工具本体不含，下载时注入）：
 *   - L2 工具内低调页脚：一行小字，非侵入（用户体感第一，不再弹 L1 提示）
 *   - ET_SITE_ORIGIN 站点源：让下载文件具备页脚官网链接与离线统计上报端点
 *
 * ES5 + 内联样式（下载文件脱离站点运行，不能引用任何外部资源）。
 */
final class BrandInjector
{
    private function __construct()
    {
    }

    /**
     * 向 HTML 注入品牌回流与站点源（</body> 前）。
     *
     * @param string $siteUrl 站点绝对地址（下载文件在 file:// 打开，必须绝对 URL）
     */
    public static function inject(string $html, string $toolId, string $title, string $siteUrl): string
    {
        $safeUrl = htmlspecialchars($siteUrl, ENT_QUOTES, 'UTF-8');

        $brand = '<div data-et-brand style="position:static!important;text-align:center;'
            . 'font:12px/1.6 system-ui,-apple-system,sans-serif;color:#9ca3af;'
            . 'padding:6px 0 10px;user-select:none;">'
            . 'KeeTools 课工具 · <a href="' . $safeUrl . '" target="_blank" rel="noopener" '
            . 'style="color:inherit;text-decoration:underline;">更多免费课堂工具</a></div>';

        /* 站点源：供 et-chrome 页脚链接与 et-stats 离线统计模块使用 */
        $origin = '<script>try{window.ET_SITE_ORIGIN="' . $safeUrl . '";}catch(e){}</script>';

        $block = "\n<!-- KeeTools 品牌回流（服务端注入，请勿移除） -->\n"
            . $origin . $brand . "\n";

        $pos = strripos($html, '</body>');
        if ($pos === false) {
            return $html . $block;
        }

        return substr($html, 0, $pos) . $block . substr($html, $pos);
    }
}
