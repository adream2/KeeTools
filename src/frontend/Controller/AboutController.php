<?php
declare(strict_types=1);

namespace App\Frontend\Controller;

use App\Core\App;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Services\ThirdPartyNotices;
use App\Services\ToolRepository;

/**
 * 关于页（/about）
 *
 * 项目介绍 + 开放素材版权说明。版权登记的唯一真源是
 * assets-src/third-party.json：本页由此自动渲染，
 * THIRD-PARTY-LICENSES.md 由 scripts/gen_licenses.py 从同一数据生成。
 * 页面数据（工具数 / 学科数）从库里实时读。
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

        $notices = ThirdPartyNotices::all();

        $html = View::render('pages/about', [
            'pageTitle'    => '关于本站 — ' . site_name(),
            'pageDesc'     => '关于 ' . site_name() . '：项目介绍与图标、音频、数据等第三方素材的版权说明',
            'toolCount'    => $toolCount,
            'subjectCount' => $subjectCount,
            'notices'      => $notices,
            'hasNotices'   => ThirdPartyNotices::hasEntries(),
        ]);

        return Response::html($html);
    }
}
