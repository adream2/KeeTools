<?php
declare(strict_types=1);

namespace App\Admin\Controller;

use App\Admin\AdminController;
use App\Core\App;
use App\Core\Config;
use App\Core\Env;
use App\Core\Request;
use App\Core\Response;
use App\Core\Security;
use App\Services\ManifestWriter;
use App\Services\SiteOps;

/**
 * 站点设置 + 系统信息（docs/站点运营模块设计.md §七）
 *
 * 设置页拆 Tab 分区保存：/admin/settings（基本，已有）、
 * /settings/footer、/settings/links、/settings/ads、/settings/announce、/settings/sponsor。
 * 每 Tab 只读写自己的键 —— 验收标准：保存「页脚」后广告位一字不变，反之亦然。
 *
 * 配置优先级是 .env > site_config > 默认值（Config 类），
 * 与 .env 同名的键在页面给出「.env 优先」提示。
 *
 * 原始 HTML 字段（广告 code）仅 admin 可写 —— 本控制器全部路由挂 role 中间件。
 */
final class SettingsController extends AdminController
{
    /** 基本 Tab 可在后台编辑的键：[key => 说明] */
    private const EDITABLE = [
        'SITE_NAME'               => '站点名称',
        'SITE_DESCRIPTION'        => '站点描述（首页 meta description）',
        'seo_home_title'          => '首页 SEO 标题（留空则用「站点名 — 免费课堂工具集」）',
        'site_keywords'           => '首页关键词（逗号分隔，留空则按学科自动生成）',
        'DOWNLOAD_DIRECT_ENABLED' => '单文件直接下载开关',
    ];

    /** 页脚 Tab 的键 */
    private const FOOTER_KEYS = [
        'footer_brand_desc'    => '品牌描述（留空取站点口号）',
        'footer_contact_email' => '联系邮箱（留空不显示；关于页等文案引导用户「通过页脚联系」）',
        'footer_contact_text'  => '其他联系方式（公众号 / QQ 等，一行一条，纯文本展示）',
        'footer_copyright'     => '版权行（支持 {year} {site_name}）',
        'footer_icp_number'    => 'ICP 备案号',
        'footer_icp_url'       => 'ICP 查询链接',
        'footer_police_number' => '公安备案号',
        'footer_police_url'    => '公安备案链接',
        'footer_statement'     => '补充声明',
    ];

    /** 页脚 Tab 的开关键 */
    private const FOOTER_SWITCHES = ['footer_show_sitemap' => '显示「站点地图」链接'];

    /** 赞助 Tab 的文本键 */
    private const SPONSOR_KEYS = [
        'sponsor_title'     => '赞助页标题',
        'sponsor_desc'      => '赞助页导语',
        'sponsor_cost_note' => '站点开销说明',
        'sponsor_note'      => '自愿声明',
        'sponsor_wechat_qr' => '微信收款码图片地址',
        'sponsor_alipay_qr' => '支付宝收款码图片地址',
        'community_qr_image' => '公众号二维码图片地址（详情页 / 中间页引流）',
        'community_qr_text'  => '公众号引导文案',
        'custom_tool_url'    => '工具定制需求入口链接（留空且无文案 = 全站不显示）',
        'custom_tool_note'   => '工具定制引导文案（详情页侧栏）',
    ];

    /** 赞助 Tab 的开关键 */
    private const SPONSOR_SWITCHES = [
        'sponsor_enabled'       => '赞助功能总开关（关 = 前台所有赞助入口消失）',
        'sponsor_show_footer'   => '页脚显示「支持本站」入口',
        'sponsor_show_home_cta' => '首页底部赞助 CTA 卡',
    ];

    /** 公告 Tab 的开关与数值键 */
    private const ANNOUNCE_SWITCHES = [
        'announce_enabled'  => '公告总开关',
        'announce_closable' => '通栏可关闭',
        'announce_center'   => '通栏文案居中',
    ];

    // ── 基本 Tab（P0 已有）─────────────────────────

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
                continue;
            }

            $value = trim($value);
            if ($key === 'SITE_NAME' && $value === '') {
                continue;
            }

            Config::set($key, $value);
            $saved++;
        }

        if ($request->post('DOWNLOAD_DIRECT_ENABLED') === null && $request->post('_submit') !== null) {
            Config::set('DOWNLOAD_DIRECT_ENABLED', '0');
        }

        return $this->redirectWith('success', '已保存 ' . $saved . ' 项设置。', '/admin/settings');
    }

    // ── 页脚 Tab ───────────────────────────────────

    public function footer(Request $request): Response
    {
        return $this->render('settings-footer', [
            'pageTitle'     => '导航与页脚 — ' . site_name(),
            'textItems'     => $this->collectTextItems(self::FOOTER_KEYS),
            'switches'      => $this->collectSwitches(self::FOOTER_SWITCHES),
            'headerNavText' => SiteOps::navToText(Config::string('header_nav')),
            'footerNavText' => SiteOps::navToText(Config::string('footer_nav')),
            'applyNote'     => Config::string('friend_link_apply_note'),
        ], 'settings');
    }

    public function saveFooter(Request $request): Response
    {
        $saved = $this->saveKeys(array_merge(
            array_keys(self::FOOTER_KEYS),
            array_keys(self::FOOTER_SWITCHES)
        ), $request);

        // 导航：每行「名称 | URL」→ JSON 存储（空 = 回退默认导航）
        foreach (['header_nav', 'footer_nav'] as $navKey) {
            if ($request->post($navKey) !== null) {
                Config::set($navKey, SiteOps::parseNavText((string) $request->post($navKey)));
                $saved++;
            }
        }

        if ($request->post('friend_link_apply_note') !== null) {
            Config::set('friend_link_apply_note', trim((string) $request->post('friend_link_apply_note')));
            $saved++;
        }

        return $this->redirectWith('success', '导航与页脚设置已保存（' . $saved . ' 项）。', '/admin/settings/footer');
    }

    // ── 友链 Tab ───────────────────────────────────

    public function links(Request $request): Response
    {
        return $this->render('settings-links', [
            'pageTitle' => '友链管理 — ' . site_name(),
            'links'     => $this->allFriendLinks(),
        ], 'settings');
    }

    public function createLink(Request $request): Response
    {
        if (!$this->dbReady()) {
            return $this->redirectWith('error', '数据库未初始化', '/admin/settings/links');
        }

        $name = trim((string) ($request->post('name') ?? ''));
        $url = Security::safeExternalUrl((string) ($request->post('url') ?? ''));
        $placement = (string) ($request->post('placement') ?? 'all');
        if ($name === '' || mb_strlen($name) > 20) {
            return $this->redirectWith('error', '名称必填且不超过 20 字', '/admin/settings/links');
        }
        if ($url === null) {
            return $this->redirectWith('error', 'URL 非法：只允许 http(s)、mailto: 或站内 / 路径', '/admin/settings/links');
        }
        if (!in_array($placement, ['home', 'sub', 'all'], true)) {
            $placement = 'all';
        }

        App::db()->execute(
            'INSERT INTO friend_links (name, url, placement, nofollow, enabled, sort_order, created_at)
             VALUES (:name, :url, :placement, :nofollow, 1, :sort, :now)',
            [
                ':name' => $name, ':url' => $url, ':placement' => $placement,
                ':nofollow' => $request->post('nofollow') === '1' ? 1 : 0,
                ':sort' => (int) ($request->post('sort_order') ?? 0),
                ':now'  => date('Y-m-d H:i:s'),
            ]
        );

        return $this->redirectWith('success', '友链已添加。', '/admin/settings/links');
    }

    /**
     * 批量添加：每行「名称 | URL | nofollow」。
     */
    public function batchLinks(Request $request): Response
    {
        if (!$this->dbReady()) {
            return $this->redirectWith('error', '数据库未初始化', '/admin/settings/links');
        }

        $lines = preg_split('/\r\n|\r|\n/', (string) ($request->post('batch') ?? '')) ?: [];
        $added = 0;
        $skipped = 0;
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $parts = array_map('trim', explode('|', $line));
            $name = $parts[0] ?? '';
            $url = Security::safeExternalUrl($parts[1] ?? '');
            if ($name === '' || $url === null) {
                $skipped++;
                continue;
            }
            App::db()->execute(
                'INSERT INTO friend_links (name, url, placement, nofollow, enabled, sort_order, created_at)
                 VALUES (:name, :url, :placement, :nofollow, 1, 0, :now)',
                [
                    ':name' => mb_substr($name, 0, 20), ':url' => $url,
                    ':placement' => 'all',
                    ':nofollow' => (($parts[2] ?? '') === '1' || strtolower($parts[2] ?? '') === 'nofollow') ? 1 : 0,
                    ':now' => date('Y-m-d H:i:s'),
                ]
            );
            $added++;
        }

        $message = '批量导入完成：成功 ' . $added . ' 条' . ($skipped > 0 ? '，跳过非法 ' . $skipped . ' 条' : '') . '。';

        return $this->redirectWith($added > 0 ? 'success' : 'warning', $message, '/admin/settings/links');
    }

    public function updateLink(Request $request): Response
    {
        $link = $this->findFriendLink($request);
        if ($link instanceof Response) {
            return $link;
        }

        $name = trim((string) ($request->post('name') ?? ''));
        $url = Security::safeExternalUrl((string) ($request->post('url') ?? ''));
        $placement = (string) ($request->post('placement') ?? 'all');
        if ($name === '' || $url === null || !in_array($placement, ['home', 'sub', 'all'], true)) {
            return $this->redirectWith('error', '保存失败：名称 / URL / 投放面非法', '/admin/settings/links');
        }

        App::db()->execute(
            'UPDATE friend_links SET name = :name, url = :url, placement = :placement,
                    nofollow = :nofollow, enabled = :enabled, sort_order = :sort WHERE id = :id',
            [
                ':name' => $name, ':url' => $url, ':placement' => $placement,
                ':nofollow' => $request->post('nofollow') === '1' ? 1 : 0,
                ':enabled' => $request->post('enabled') === '1' ? 1 : 0,
                ':sort' => (int) ($request->post('sort_order') ?? 0),
                ':id' => (int) $link['id'],
            ]
        );

        return $this->redirectWith('success', '友链已更新。', '/admin/settings/links');
    }

    public function deleteLink(Request $request): Response
    {
        $link = $this->findFriendLink($request);
        if ($link instanceof Response) {
            return $link;
        }

        App::db()->execute('DELETE FROM friend_links WHERE id = :id', [':id' => (int) $link['id']]);

        return $this->redirectWith('success', '友链已删除。', '/admin/settings/links');
    }

    // ── 广告位 Tab ─────────────────────────────────

    public function ads(Request $request): Response
    {
        $slots = [];
        foreach (SiteOps::AD_SLOTS as $slot) {
            $row = $this->dbReady()
                ? App::db()->fetch('SELECT * FROM ad_slots WHERE slot = :slot', [':slot' => $slot])
                : null;
            $slots[$slot] = [
                'label'      => self::AD_SLOT_LABELS[$slot] ?? $slot,
                'enabled'    => $row !== null && (int) $row['enabled'] === 1,
                'code'       => (string) ($row['code'] ?? ''),
                'device'     => (string) ($row['device'] ?? 'all'),
                'start_date' => (string) ($row['start_date'] ?? ''),
                'end_date'   => (string) ($row['end_date'] ?? ''),
            ];
        }

        return $this->render('settings-ads', [
            'pageTitle' => '广告位 — ' . site_name(),
            'slots'     => $slots,
        ], 'settings');
    }

    /**
     * 按槽增量保存：只更新提交的槽位字段，其他槽一字不动（分区保存验收）。
     */
    public function saveAds(Request $request): Response
    {
        if (!$this->dbReady()) {
            return $this->redirectWith('error', '数据库未初始化', '/admin/settings/ads');
        }

        $now = date('Y-m-d H:i:s');
        foreach (SiteOps::AD_SLOTS as $slot) {
            $enabled = $request->post('ads_' . $slot . '_enabled');
            if ($enabled === null && $request->post('ads_' . $slot . '_present') === null) {
                continue; // 该槽不在本次提交中
            }

            $device = (string) ($request->post('ads_' . $slot . '_device') ?? 'all');
            if (!in_array($device, ['all', 'desktop', 'mobile'], true)) {
                $device = 'all';
            }

            $start = trim((string) ($request->post('ads_' . $slot . '_start') ?? ''));
            $end = trim((string) ($request->post('ads_' . $slot . '_end') ?? ''));
            if ($start !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $start)) {
                $start = '';
            }
            if ($end !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) {
                $end = '';
            }

            $updated = App::db()->execute(
                'UPDATE ad_slots SET enabled = :enabled, code = :code, device = :device,
                        start_date = :start, end_date = :end, updated_at = :now WHERE slot = :slot',
                [
                    ':enabled' => $enabled === '1' ? 1 : 0,
                    ':code'    => (string) ($request->post('ads_' . $slot . '_code') ?? ''),
                    ':device'  => $device,
                    ':start'   => $start !== '' ? $start : null,
                    ':end'     => $end !== '' ? $end : null,
                    ':now'     => $now,
                    ':slot'    => $slot,
                ]
            );

            if ($updated === 0) {
                App::db()->execute(
                    'INSERT INTO ad_slots (slot, enabled, code, device, start_date, end_date, updated_at)
                     VALUES (:slot, :enabled, :code, :device, :start, :end, :now)',
                    [
                        ':slot' => $slot, ':enabled' => $enabled === '1' ? 1 : 0,
                        ':code' => (string) ($request->post('ads_' . $slot . '_code') ?? ''),
                        ':device' => $device,
                        ':start' => $start !== '' ? $start : null,
                        ':end' => $end !== '' ? $end : null,
                        ':now' => $now,
                    ]
                );
            }
        }

        return $this->redirectWith('success', '广告位已保存（按槽增量更新）。', '/admin/settings/ads');
    }

    // ── 公告 Tab ───────────────────────────────────

    public function announce(Request $request): Response
    {
        $items = $this->dbReady() ? App::db()->fetchAll(
            'SELECT * FROM announcements ORDER BY pinned DESC, sort_order ASC, id DESC'
        ) : [];

        return $this->render('settings-announce', [
            'pageTitle' => '公告管理 — ' . site_name(),
            'items'     => $items,
            'switches'  => $this->collectSwitches(self::ANNOUNCE_SWITCHES),
            'barCount'  => SiteOps::announceBarCount(),
        ], 'settings');
    }

    public function saveAnnounceSettings(Request $request): Response
    {
        $saved = $this->saveKeys(array_keys(self::ANNOUNCE_SWITCHES), $request);

        $barCount = (int) ($request->post('announce_bar_count') ?? 1);
        Config::set('announce_bar_count', (string) min(3, max(1, $barCount)));
        $saved++;

        return $this->redirectWith('success', '公告设置已保存（' . $saved . ' 项）。', '/admin/settings/announce');
    }

    public function createAnnounce(Request $request): Response
    {
        if (!$this->dbReady()) {
            return $this->redirectWith('error', '数据库未初始化', '/admin/settings/announce');
        }

        $text = trim((string) ($request->post('text') ?? ''));
        if ($text === '') {
            return $this->redirectWith('error', '公告内容不能为空', '/admin/settings/announce');
        }

        $type = (string) ($request->post('type') ?? 'info');
        if (!in_array($type, ['info', 'update', 'warning'], true)) {
            $type = 'info';
        }

        App::db()->execute(
            'INSERT INTO announcements (type, text, link, link_text, pinned, enabled, start_date, end_date, sort_order, created_at)
             VALUES (:type, :text, :link, :link_text, :pinned, :enabled, :start, :end, :sort, :now)',
            [
                ':type' => $type,
                ':text' => $text,
                ':link' => Security::safeExternalUrl((string) ($request->post('link') ?? '')),
                ':link_text' => trim((string) ($request->post('link_text') ?? '')) ?: null,
                ':pinned' => $request->post('pinned') === '1' ? 1 : 0,
                ':enabled' => $request->post('enabled') === '1' ? 1 : 0,
                ':start' => self::dateOrNull((string) ($request->post('start_date') ?? '')),
                ':end' => self::dateOrNull((string) ($request->post('end_date') ?? '')),
                ':sort' => (int) ($request->post('sort_order') ?? 0),
                ':now' => date('Y-m-d H:i:s'),
            ]
        );

        return $this->redirectWith('success', '公告已添加。', '/admin/settings/announce');
    }

    public function toggleAnnounce(Request $request): Response
    {
        $item = $this->findAnnounce($request);
        if ($item instanceof Response) {
            return $item;
        }

        App::db()->execute(
            'UPDATE announcements SET enabled = :enabled WHERE id = :id',
            [':enabled' => (int) $item['enabled'] === 1 ? 0 : 1, ':id' => (int) $item['id']]
        );

        return $this->redirectWith('success', '公告状态已切换。', '/admin/settings/announce');
    }

    public function deleteAnnounce(Request $request): Response
    {
        $item = $this->findAnnounce($request);
        if ($item instanceof Response) {
            return $item;
        }

        App::db()->execute('DELETE FROM announcements WHERE id = :id', [':id' => (int) $item['id']]);

        return $this->redirectWith('success', '公告已删除。', '/admin/settings/announce');
    }

    // ── 赞助 Tab ───────────────────────────────────

    public function sponsor(Request $request): Response
    {
        $thanks = $this->dbReady() ? App::db()->fetchAll(
            'SELECT * FROM sponsor_thanks ORDER BY id DESC LIMIT 100'
        ) : [];

        return $this->render('settings-sponsor', [
            'pageTitle' => '赞助设置 — ' . site_name(),
            'textItems' => $this->collectTextItems(self::SPONSOR_KEYS),
            'switches'  => $this->collectSwitches(self::SPONSOR_SWITCHES),
            'thanks'    => $thanks,
        ], 'settings');
    }

    public function saveSponsor(Request $request): Response
    {
        $saved = $this->saveKeys(array_merge(
            array_keys(self::SPONSOR_KEYS),
            array_keys(self::SPONSOR_SWITCHES)
        ), $request);

        return $this->redirectWith('success', '赞助设置已保存（' . $saved . ' 项）。', '/admin/settings/sponsor');
    }

    public function createThanks(Request $request): Response
    {
        if (!$this->dbReady()) {
            return $this->redirectWith('error', '数据库未初始化', '/admin/settings/sponsor');
        }

        $name = trim((string) ($request->post('name') ?? ''));
        if ($name === '' || mb_strlen($name) > 20) {
            return $this->redirectWith('error', '留名必填且不超过 20 字', '/admin/settings/sponsor');
        }

        App::db()->execute(
            'INSERT INTO sponsor_thanks (name, note, amount, created_at) VALUES (:name, :note, :amount, :now)',
            [
                ':name' => $name,
                ':note' => mb_substr(trim((string) ($request->post('note') ?? '')), 0, 60),
                ':amount' => trim((string) ($request->post('amount') ?? '')) ?: null,
                ':now' => date('Y-m-d H:i:s'),
            ]
        );

        return $this->redirectWith('success', '鸣谢已添加。', '/admin/settings/sponsor');
    }

    public function deleteThanks(Request $request): Response
    {
        if (!$this->dbReady()) {
            return $this->redirectWith('error', '数据库未初始化', '/admin/settings/sponsor');
        }

        App::db()->execute(
            'DELETE FROM sponsor_thanks WHERE id = :id',
            [':id' => (int) $request->attribute('id', 0)]
        );

        return $this->redirectWith('success', '鸣谢已删除。', '/admin/settings/sponsor');
    }

    // ── 系统信息（P0 已有）─────────────────────────

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
                'curl'       => extension_loaded('curl'),
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

    /**
     * 把降级覆盖落盘回 manifest（全部工具）。
     */
    public function flushOverrides(Request $request): Response
    {
        if (!$this->dbReady()) {
            return $this->redirectWith('error', '数据库未初始化', '/admin/system');
        }

        $writer = new ManifestWriter();
        $rows = App::db()->fetchAll('SELECT tool_id FROM tool_overrides');
        $ok = 0;
        foreach ($rows as $row) {
            if ($writer->flushOverride((string) $row['tool_id'])) {
                $ok++;
            }
        }

        return $this->redirectWith('success', '已将 ' . $ok . ' 个工具的覆盖写入 manifest。', '/admin/system');
    }

    // ── 私有辅助 ───────────────────────────────────

    private const AD_SLOT_LABELS = [
        'home_top'      => '首页顶部（Hero 上方）',
        'list_top'      => '列表页顶部',
        'list_bottom'   => '列表页底部',
        'detail_side'   => '详情页侧栏',
        'detail_bottom' => '详情页底部',
        'footer'        => '页脚（备案行上方）',
    ];

    /**
     * @return array<string, array{label: string, value: string, inEnv: bool}>
     */
    private function collectTextItems(array $keys): array
    {
        $items = [];
        foreach ($keys as $key => $label) {
            $items[$key] = [
                'label' => $label,
                'value' => Config::string($key),
                'inEnv' => Env::get($key) !== null,
            ];
        }

        return $items;
    }

    /**
     * @return array<string, array{label: string, value: bool}>
     */
    private function collectSwitches(array $keys): array
    {
        $items = [];
        foreach ($keys as $key => $label) {
            $items[$key] = ['label' => $label, 'value' => Config::bool($key, false)];
        }

        return $items;
    }

    /**
     * 分区保存核心：只写白名单内的键。checkbox 未提交 = 关闭（补 0）。
     *
     * @param list<string> $keys
     */
    private function saveKeys(array $keys, Request $request): int
    {
        $saved = 0;
        $switches = array_merge(self::FOOTER_SWITCHES, self::SPONSOR_SWITCHES, self::ANNOUNCE_SWITCHES);
        $hasSubmit = $request->post('_submit') !== null || $request->post('_submitting') !== null;

        foreach ($keys as $key) {
            $value = $request->post($key);
            if ($value === null) {
                // 开关类：本次表单包含（有提交动作）而键缺失 = 未勾选 → 0
                if ($hasSubmit && isset($switches[$key])) {
                    Config::set($key, '0');
                    $saved++;
                }
                continue;
            }
            Config::set($key, trim($value));
            $saved++;
        }

        return $saved;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function allFriendLinks(): array
    {
        if (!$this->dbReady()) {
            return [];
        }

        $rows = App::db()->fetchAll('SELECT * FROM friend_links ORDER BY sort_order ASC, id ASC');

        $valid = [];
        foreach ($rows as &$row) {
            $row['id'] = (int) $row['id'];
            $row['nofollow'] = (int) $row['nofollow'] === 1;
            $row['enabled'] = (int) $row['enabled'] === 1;
            $row['url_valid'] = Security::safeExternalUrl((string) $row['url']) !== null;
        }
        unset($row);
        $valid = $rows;

        return $valid;
    }

    /**
     * @return array<string, mixed>|Response
     */
    private function findFriendLink(Request $request): array|Response
    {
        if (!$this->dbReady()) {
            return $this->redirectWith('error', '数据库未初始化', '/admin/settings/links');
        }

        $link = App::db()->fetch(
            'SELECT * FROM friend_links WHERE id = :id',
            [':id' => (int) $request->attribute('id', 0)]
        );
        if ($link === null) {
            return $this->redirectWith('error', '友链不存在', '/admin/settings/links');
        }

        return $link;
    }

    /**
     * @return array<string, mixed>|Response
     */
    private function findAnnounce(Request $request): array|Response
    {
        if (!$this->dbReady()) {
            return $this->redirectWith('error', '数据库未初始化', '/admin/settings/announce');
        }

        $item = App::db()->fetch(
            'SELECT * FROM announcements WHERE id = :id',
            [':id' => (int) $request->attribute('id', 0)]
        );
        if ($item === null) {
            return $this->redirectWith('error', '公告不存在', '/admin/settings/announce');
        }

        return $item;
    }

    private static function dateOrNull(string $value): ?string
    {
        $value = trim($value);
        if ($value === '' || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        return $value;
    }
}
