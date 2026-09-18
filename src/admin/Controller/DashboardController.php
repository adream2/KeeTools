<?php
declare(strict_types=1);

namespace App\Admin\Controller;

use App\Admin\AdminController;
use App\Core\App;
use App\Core\Request;
use App\Core\Response;

/**
 * 后台仪表盘：核心计数 + 快捷入口 + 环境健康检查
 */
final class DashboardController extends AdminController
{
    public function index(Request $request): Response
    {
        $stats = [
            'tools'      => 0,
            'published'  => 0,
            'featured'   => 0,
            'mismatch'   => 0,
            'categories' => 0,
            'netdisks'   => 0,
        ];

        if ($this->dbReady()) {
            $db = App::db();
            $stats['tools'] = (int) $db->fetchColumn('SELECT COUNT(*) FROM tools');
            $stats['published'] = (int) $db->fetchColumn('SELECT COUNT(*) FROM tools WHERE is_published = 1');
            $stats['featured'] = (int) $db->fetchColumn('SELECT COUNT(*) FROM tools WHERE is_featured = 1');
            $stats['mismatch'] = (int) $db->fetchColumn('SELECT COUNT(*) FROM tools WHERE meta_mismatch = 1');
            $stats['categories'] = (int) $db->fetchColumn('SELECT COUNT(*) FROM categories');
            $stats['netdisks'] = (int) $db->fetchColumn('SELECT COUNT(*) FROM tool_netdisks');
        }

        return $this->render('dashboard', [
            'pageTitle'   => '仪表盘 — ' . site_name(),
            'stats'       => $stats,
            'toolsWritable'   => is_writable(App::path('tools')),
            'storageWritable' => is_writable(App::path('storage')),
            'envFileExists'   => is_file(App::path('.env')),
        ], 'dashboard');
    }
}
