<?php
declare(strict_types=1);

namespace App\Services;

/**
 * 品牌回流注入器（docs/需求文档-v2.md §11.4）
 *
 * 单文件下载时由服务端强制植入两层回流触点（工具本体不含，下载时注入）：
 *   - L1 首次运行一次性轻提示：toast，可永久关闭，localStorage 记忆
 *   - L2 工具内低调页脚：一行小字，非侵入
 *
 * ES5 + 内联样式（下载文件脱离站点运行，不能引用任何外部资源）。
 */
final class BrandInjector
{
    private function __construct()
    {
    }

    /**
     * 向 HTML 注入品牌回流（</body> 前）。
     *
     * @param string $siteUrl 站点绝对地址（下载文件在 file:// 打开，必须绝对 URL）
     */
    public static function inject(string $html, string $toolId, string $title, string $siteUrl): string
    {
        $safeId = preg_replace('/[^a-z0-9-]/', '', strtolower($toolId)) ?? '';
        $safeUrl = htmlspecialchars($siteUrl, ENT_QUOTES, 'UTF-8');

        $brand = '<div data-et-brand style="position:static!important;text-align:center;'
            . 'font:12px/1.6 system-ui,-apple-system,sans-serif;color:#9ca3af;'
            . 'padding:6px 0 10px;user-select:none;">'
            . 'KeeTools 课工具 · <a href="' . $safeUrl . '" target="_blank" rel="noopener" '
            . 'style="color:inherit;text-decoration:underline;">更多免费课堂工具</a></div>';

        $toast = "<script>(function(){try{var K='et_brand_tip_v1';"
            . "if(localStorage.getItem(K)){return;}var m=document.createElement('div');"
            . "m.setAttribute('data-et-brand','1');"
            . "m.style.cssText='position:fixed;left:12px;bottom:12px;max-width:260px;z-index:2147483000;"
            . "background:#fff;border:1px solid #e5e7eb;border-radius:10px;box-shadow:0 4px 16px rgba(0,0,0,.12);"
            . "padding:10px 12px;font:12px/1.6 system-ui,-apple-system,sans-serif;color:#374151;';"
            . "m.innerHTML='<b>KeeTools 课工具</b><br>更多免费课堂工具：<a href=\"" . $safeUrl
            . "\" target=\\\"_blank\\\" rel=\\\"noopener\\\">点击访问</a>"
            . " <a href=\\\"javascript:void(0)\\\" id=\\\"et-brand-x\\\">关闭</a>';"
            . "document.body.appendChild(m);"
            . "document.getElementById('et-brand-x').onclick=function(){m.remove();try{localStorage.setItem(K,'1')}catch(e){}};"
            . "setTimeout(function(){try{m.remove()}catch(e){}},15000);}catch(e){}})();</script>";

        $block = "\n<!-- KeeTools 品牌回流（服务端注入，请勿移除） -->\n" . $brand . $toast . "\n";

        $pos = strripos($html, '</body>');
        if ($pos === false) {
            return $html . $block;
        }

        return substr($html, 0, $pos) . $block . substr($html, $pos);
    }
}
