<?php
declare(strict_types=1);

namespace App\Frontend\Controller;

use App\Core\App;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Services\ToolRepository;

/**
 * 关于页（/about）
 *
 * 项目介绍 + 开放素材版权说明（THIRD-PARTY-LICENSES.md 的前台展示版）：
 * 图标集（Lucide / Tabler）、拼音点读音频（CC BY-SA）、汉字笔顺数据（Arphic）
 * 均须在本页保留署名与许可声明，页面数据（工具数 / 学科数）从库里实时读。
 */
final class AboutController
{
    public function index(Request $request): Response
    {
        $toolCount = 0;
        $subjectCount = 0;

        if (App::hasDb()) {
            $repository = new ToolRepository();
            $toolCount = count($repository->latest(1000));
            $subjectCount = count($repository->subjectDistribution(100));
        }

        $html = View::render('pages/about', [
            'pageTitle'    => '关于本站 — ' . site_name(),
            'pageDesc'     => '关于 ' . site_name() . '：项目介绍与图标、音频、数据等第三方素材的版权说明',
            'toolCount'    => $toolCount,
            'subjectCount' => $subjectCount,
        ]);

        return Response::html($html);
    }
}
