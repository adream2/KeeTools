<?php
declare(strict_types=1);

namespace App\Frontend\Controller;

use App\Core\App;
use App\Core\Config;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Services\ToolRepository;

/**
 * 前台首页
 *
 * 推荐工具（is_featured）不足时用最新工具补齐，
 * 保证冷启动（刚扫描完、还没人工推荐）首页不空。
 */
final class HomeController
{
    public function index(Request $request): Response
    {
        // 数据库未初始化时给出明确指引，而不是抛 500。
        // 首次 clone 后还没跑 init_db.php 是正常路径。
        $dbReady = App::hasDb();

        $featured = [];
        $stages = [];

        if ($dbReady) {
            $repository = new ToolRepository();
            $featured = $repository->featured(6);
            if ($featured === []) {
                $featured = $repository->latest(6);
            }
            $stages = $repository->stageCards();
        }

        // 首页 TDK（P4 §三）：标题与关键词均可在后台覆盖，未覆盖时按学科自动生成
        $customTitle = trim(Config::string('seo_home_title'));
        $title = $customTitle !== '' ? $customTitle : (site_name() . ' — 免费课堂工具集');

        $keywords = trim(Config::string('site_keywords'));
        if ($keywords === '') {
            $subjectNames = [];
            foreach ($stages as $stage) {
                foreach ($stage['subjects'] as $subject) {
                    if ($subject['count'] > 0) {
                        $subjectNames[] = (string) $subject['name'];
                    }
                }
            }
            $keywords = implode(',', array_slice(array_unique(array_merge(
                ['课堂工具', '教学工具', '免费教学软件', '免安装', '断网可用', '大屏投影'],
                $subjectNames,
            )), 0, 24));
        }

        $html = View::render('pages/home', [
            'pageTitle'    => $title,
            'pageDesc'     => site_description(),
            'pageKeywords' => $keywords,
            'dbReady'      => $dbReady,
            'featured'     => $featured,
            'stages'       => $stages,
        ]);

        return Response::html($html);
    }
}
