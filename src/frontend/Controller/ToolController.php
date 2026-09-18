<?php
declare(strict_types=1);

namespace App\Frontend\Controller;

use App\Core\App;
use App\Core\Request;
use App\Core\Response;
use App\Core\Security;
use App\Core\View;
use App\Services\ToolRepository;

/**
 * 工具详情页（/tool/{id}）
 *
 * P0 范围：介绍 + 标签 + 版本号 + 元信息 + 同族推荐。
 * iframe 在线使用与下载按钮属 P1（需统计注入与转化页），此处不放入口。
 */
final class ToolController
{
    public function detail(Request $request): Response
    {
        if (!App::hasDb()) {
            return page_not_found('数据库未初始化，请先执行 php scripts/init_db.php');
        }

        /** @var string $toolId 路由参数，格式已由路由段限定为非空非斜杠 */
        $toolId = (string) $request->attribute('id', '');
        if (!Security::isValidToolId($toolId)) {
            return page_not_found('工具不存在');
        }

        $repository = new ToolRepository();
        $tool = $repository->find($toolId);
        if ($tool === null) {
            return page_not_found('工具不存在: ' . $toolId);
        }

        // 目录被删但库还在（扫描前 / missing 状态）→ 不给前台开放入口
        if (!is_dir(App::path('tools') . DIRECTORY_SEPARATOR . $tool['dir_path'])) {
            return page_not_found('工具文件缺失: ' . $toolId);
        }

        $stage = $repository->primaryStageForTool($toolId);
        $related = $tool['family'] !== null
            ? $repository->relatedByFamily((string) $tool['family'], $toolId)
            : [];

        $html = View::render('pages/tool', [
            'pageTitle' => $tool['title'] . ' v' . $tool['version'] . ' — ' . site_name(),
            'pageDesc'  => $tool['description'],
            'tool'      => $tool,
            'stage'     => $stage,
            'related'   => $related,
        ]);

        return Response::html($html);
    }
}
