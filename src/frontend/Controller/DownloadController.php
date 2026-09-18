<?php
declare(strict_types=1);

namespace App\Frontend\Controller;

use App\Core\App;
use App\Core\Config;
use App\Core\Request;
use App\Core\Response;
use App\Core\Security;
use App\Services\BrandInjector;
use App\Services\RateLimiter;
use App\Services\StatsService;
use App\Services\ToolRepository;
use RuntimeException;

/**
 * 单文件直接下载（GET /download/{id}）
 *
 * 轨道 B（免费尝鲜）：次级入口，受 DOWNLOAD_DIRECT_ENABLED 总开关控制。
 * 服务端强制注入品牌回流（§11.4），统计 download_direct（允许丢失，不做补偿）。
 *
 * 文件流式输出不整读内存：注入需要全文，单文件目标 < 1MB，
 * 读取成本可接受；超大的多文件工具根本不会进入本端点（single_file 校验）。
 */
final class DownloadController
{
    public function download(Request $request): Response
    {
        if (!Config::bool('DOWNLOAD_DIRECT_ENABLED', true)) {
            return page_not_found('下载功能未开放');
        }

        if (!App::hasDb()) {
            return page_not_found('数据库未初始化');
        }

        $toolId = (string) $request->attribute('id', '');
        if (!Security::isValidToolId($toolId)) {
            return page_not_found('工具不存在');
        }

        $tool = (new ToolRepository())->find($toolId);
        if ($tool === null) {
            return page_not_found('工具不存在');
        }

        if (!$tool['single_file']) {
            return page_not_found('该工具由多个文件组成，请通过合集包获取');
        }

        // 限流：单 IP 每分钟 10 次，防脚本批量拉取
        if (!RateLimiter::hit('download', 10, 60)) {
            return Response::text('下载过于频繁，请稍后再试', 429);
        }

        $entry = (string) ($tool['entry'] !== '' ? $tool['entry'] : 'index.html');
        try {
            $file = Security::safePath(App::path('tools'), (string) $tool['dir_path'], $entry);
        } catch (RuntimeException) {
            return page_not_found('工具文件缺失');
        }

        $html = @file_get_contents($file);
        if ($html === false) {
            return page_not_found('工具文件读取失败');
        }

        StatsService::record('download_direct', $toolId);

        // 品牌回流：下载文件脱离站点运行，链接必须是绝对地址
        $html = BrandInjector::inject($html, $toolId, (string) $tool['title'], site_url());

        $name = $tool['title'] . ' v' . $tool['version'] . ' - KeeTools.html';
        $ascii = preg_replace('/[^\x20-\x7E]/', '_', $name) ?? 'download.html';

        return Response::html($html)
            ->withHeader(
                'Content-Disposition',
                'attachment; filename="' . $ascii . '"; filename*=UTF-8\'\'' . rawurlencode($name)
            );
    }
}
