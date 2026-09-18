<?php
declare(strict_types=1);

namespace App\Admin\Controller;

use App\Admin\AdminController;
use App\Core\App;
use App\Core\Config;
use App\Core\Env;
use App\Core\Request;
use App\Core\Response;

/**
 * 站点设置 + 系统信息
 *
 * 配置优先级是 .env > site_config > 默认值（Config 类）。
 * 因此这里保存到 site_config 的项若同时定义在 .env，会被 .env 覆盖——
 * 页面上对这类项给出「.env 优先」提示，避免站长改了不生效而困惑。
 */
final class SettingsController extends AdminController
{
    /** 可在后台编辑的键：[key => 说明] */
    private const EDITABLE = [
        'SITE_NAME'               => '站点名称',
        'SITE_DESCRIPTION'        => '站点描述（SEO）',
        'DOWNLOAD_DIRECT_ENABLED' => '单文件直接下载开关',
    ];

    public function index(Request $request): Response
    {
        $items = [];
        foreach (self::EDITABLE as $key => $label) {
            $items[$key] = [
                'label'    => $label,
                'value'    => Config::string($key),
                'inEnv'    => Env::get($key) !== null,
                'inDb'     => isset(Config::allSiteConfig()[$key]),
                'isSwitch' => in_array($key, ['DOWNLOAD_DIRECT_ENABLED'], true),
            ];
        }

        return $this->render('settings', [
            'pageTitle' => '站点设置 — ' . site_name(),
            'items'     => $items,
        ], 'settings');
    }

    public function save(Request $request): Response
    {
        if (!$this->dbReady()) {
            return $this->redirectWith('error', '数据库未初始化', '/admin/settings');
        }

        $saved = 0;
        foreach (array_keys(self::EDITABLE) as $key) {
            $value = $request->post($key);
            if ($value === null) {
                continue; // 表单未包含该键（如开关未勾选时由下方补 0）
            }

            $value = trim($value);
            if ($key === 'SITE_NAME' && $value === '') {
                continue; // 站点名不允许为空
            }

            Config::set($key, $value);
            $saved++;
        }

        // 未勾选的开关补 0（checkbox 不提交即缺省）
        if ($request->post('DOWNLOAD_DIRECT_ENABLED') === null && $request->post('_submit') !== null) {
            Config::set('DOWNLOAD_DIRECT_ENABLED', '0');
        }

        return $this->redirectWith('success', '已保存 ' . $saved . ' 项设置。', '/admin/settings');
    }

    public function system(Request $request): Response
    {
        $sqliteVersion = '';
        if ($this->dbReady()) {
            $sqliteVersion = (string) App::db()->fetchColumn('SELECT sqlite_version()');
        }

        $envBool = static fn (string $key): string => Env::bool($key) ? '开启' : '关闭';
        $secretState = static fn (string $key): string => ((Env::get($key, '') ?? '') !== '') ? '已设置' : '未设置';

        $appKey = (string) (Env::get('APP_KEY', '') ?? '');
        $statsDbSize = is_file(App::path('storage/stats.db')) ? filesize(App::path('storage/stats.db')) : 0;
        $appDbSize = is_file(App::path('storage/app.db')) ? filesize(App::path('storage/app.db')) : 0;

        return $this->render('system', [
            'pageTitle' => '系统信息 — ' . site_name(),
            'phpVersion'      => PHP_VERSION,
            'sqliteVersion'   => $sqliteVersion,
            'appEnv'          => Env::get('APP_ENV', 'local') ?: 'local',
            'appDebug'        => $envBool('APP_DEBUG'),
            'appKeyState'     => $secretState('APP_KEY'),
            'appKeyValid'     => preg_match('/^[0-9a-f]{64}$/i', $appKey) === 1,
            'adminPassword'   => $secretState('ADMIN_PASSWORD'),
            'passwordless'    => $envBool('ADMIN_PASSWORDLESS'),
            'passwordlessIps' => Env::list('ADMIN_PASSWORDLESS_IPS'),
            'timezone'        => date_default_timezone_get(),
            'extensions'      => [
                'pdo_sqlite' => extension_loaded('pdo_sqlite'),
                'mbstring'   => extension_loaded('mbstring'),
                'json'       => extension_loaded('json'),
                'fileinfo'   => extension_loaded('fileinfo'),
            ],
            'paths' => [
                '项目根'    => App::path(),
                'tools/'   => App::path('tools'),
                'storage/' => App::path('storage'),
            ],
            'writable' => [
                'tools/'    => is_writable(App::path('tools')),
                'storage/'  => is_writable(App::path('storage')),
                'var/logs/' => is_writable(App::path('var/logs')),
            ],
            'dbSizes' => [
                'app.db'   => $appDbSize,
                'stats.db' => $statsDbSize,
            ],
            'overrideCount' => $this->dbReady()
                ? (int) App::db()->fetchColumn('SELECT COUNT(*) FROM tool_overrides')
                : 0,
        ], 'system');
    }
}
