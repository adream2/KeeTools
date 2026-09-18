<?php
declare(strict_types=1);

namespace App\Frontend\Controller;

use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Services\SiteOps;

/**
 * 友链独立页（/friend-links）
 *
 * 内页友链 = 独立页面（用户定稿）：展示全部启用友链 + 申请友链说明。
 * 页脚「友情链接」入口指向本页；「始终在页脚显示」的友链同时出现在全站页脚。
 */
final class FriendLinkController
{
    public function index(Request $request): Response
    {
        $links = SiteOps::allFriendLinks();

        // 页面零痕迹：无友链且未配置申请说明时返回 404，
        // 避免出现一个空页被搜索引擎收录
        if ($links === [] && trim(\App\Core\Config::string('friend_link_apply_note')) === '') {
            return page_not_found('页面不存在');
        }

        $html = View::render('pages/friend-links', [
            'pageTitle' => '友情链接 — ' . site_name(),
            'pageDesc'  => '友情链接与申请友链说明 — ' . site_name(),
            'links'     => $links,
            'applyNote' => SiteOps::friendApplyNote(),
        ]);

        return Response::html($html);
    }
}
