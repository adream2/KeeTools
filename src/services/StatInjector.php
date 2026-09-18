<?php
declare(strict_types=1);

namespace App\Services;

/**
 * 统计脚本注入器（docs/需求文档-v2.md §10.2 关键决策）
 *
 * 在线使用页由服务端向工具 HTML 的 </body> 前注入一段上报脚本：
 *   - 独立 IIFE + ES5（教室 Win7 + 老版 Chrome / 360 也能跑）
 *   - 不依赖工具任何变量、不污染全局命名空间、出错完全静默
 *   - 下载的单文件不经过本流程，保持绝对干净
 *
 * 服务端在输出使用页时已直记 use_online，脚本上报为第二通道；
 * StatsService 按 IP+工具去重窗口合并，两通道并存不会双计。
 */
final class StatInjector
{
    private function __construct()
    {
    }

    /**
     * 向 HTML 注入上报脚本。找不到 </body> 时追加到末尾（浏览器容错）。
     */
    public static function inject(string $html, string $toolId): string
    {
        $script = self::script($toolId);

        $pos = strripos($html, '</body>');
        if ($pos === false) {
            return $html . $script;
        }

        return substr($html, 0, $pos) . $script . substr($html, $pos);
    }

    /**
     * 上报脚本本体。用 XHR 而非 fetch：老内核兼容性最稳。
     */
    private static function script(string $toolId): string
    {
        $safeId = preg_replace('/[^a-z0-9-]/', '', strtolower($toolId)) ?? '';

        return "\n<script>(function(){try{var x=new XMLHttpRequest();"
            . "x.open('POST','/api/track',true);"
            . "x.setRequestHeader('Content-Type','application/json');"
            . "x.send(JSON.stringify({event:'use_online',tool:'{$safeId}'}));}catch(e){}})();</script>\n";
    }
}
