<?php
declare(strict_types=1);

namespace App\Frontend\Controller;

use App\Core\App;
use App\Core\Request;
use App\Core\Response;
use App\Core\Security;
use App\Core\View;
use App\Services\NetdiskRepository;
use App\Services\StatInjector;
use App\Services\StatsService;
use App\Services\ToolRepository;
use RuntimeException;

/**
 * 工具详情页 + 在线使用页
 *
 * 详情页（/tool/{id}）：转化页 —— 预览 / 合集包主按钮 / 单文件下载链。
 * 使用页（/tool/{id}/use）：iframe 全屏加载工具，服务端注入统计脚本（§10.2）。
 */
final class ToolController
{
    public function detail(Request $request): Response
    {
        $tool = $this->resolveTool($request);
        if ($tool instanceof Response) {
            return $tool;
        }
        $toolId = (string) $tool['tool_id'];

        $repository = new ToolRepository();
        $stage = $repository->primaryStageForTool($toolId);
        $related = $tool['family'] !== null
            ? $repository->relatedByFamily((string) $tool['family'], $toolId)
            : [];

        $netdisks = (new NetdiskRepository())->forTool($toolId);

        // 详情页浏览：服务端直记，不依赖前台 JS
        StatsService::record('view', $toolId);

        $html = View::render('pages/tool', [
            'pageTitle' => $tool['title'] . ' v' . $tool['version'] . ' — ' . site_name(),
            'pageDesc'  => $tool['description'],
            'tool'      => $tool,
            'stage'     => $stage,
            'related'   => $related,
            'netdisks'  => $netdisks,
        ]);

        return Response::html($html);
    }

    /**
     * 在线使用页：直接输出工具 HTML（同源 iframe）。
     *
     * 服务端直记 use_online（可靠通道），并向 </body> 前注入上报脚本
     * （去重窗口防双计）。多文件工具无法在单一路由下解析相对资源，
     * 降级为引导页（工具例外清单场景，需求 §工具尽量单文件）。
     */
    public function use(Request $request): Response
    {
        $tool = $this->resolveTool($request);
        if ($tool instanceof Response) {
            return $tool;
        }
        $toolId = (string) $tool['tool_id'];

        if (!$tool['single_file']) {
            // 多文件工具：无统计（未真实使用），引导下载
            $html = View::render('pages/use-fallback', [
                'pageTitle' => '在线使用 — ' . $tool['title'],
                'tool'      => $tool,
            ]);

            return Response::html($html);
        }

        try {
            $file = Security::safePath(
                App::path('tools'),
                (string) $tool['dir_path'],
                (string) ($tool['entry'] !== '' ? $tool['entry'] : 'index.html')
            );
        } catch (RuntimeException) {
            return page_not_found('工具文件缺失: ' . $toolId);
        }

        $html = @file_get_contents($file);
        if ($html === false) {
            return page_not_found('工具文件读取失败: ' . $toolId);
        }

        StatsService::record('use_online', $toolId);

        return Response::html(StatInjector::inject($html, $toolId));
    }

    /**
     * 解析并校验工具：id 格式 → 已上架 → 目录存在。
     * 三处共用，返回 Response 表示校验失败（直接返回给调用方）。
     *
     * @return array<string, mixed>|Response
     */
    private function resolveTool(Request $request): array|Response
    {
        if (!App::hasDb()) {
            return page_not_found('数据库未初始化，请先执行 php scripts/init_db.php');
        }

        /** @var string $toolId 路由参数，格式已由路由段限定为非空非斜杠 */
        $toolId = (string) $request->attribute('id', '');
        if (!Security::isValidToolId($toolId)) {
            return page_not_found('工具不存在');
        }

        $tool = (new ToolRepository())->find($toolId);
        if ($tool === null) {
            return page_not_found('工具不存在: ' . $toolId);
        }

        // 目录被删但库还在（扫描前 / missing 状态）→ 不给前台开放入口
        if (!is_dir(App::path('tools') . DIRECTORY_SEPARATOR . $tool['dir_path'])) {
            return page_not_found('工具文件缺失: ' . $toolId);
        }

        return $tool;
    }
}
