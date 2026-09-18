<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\App;
use App\Core\Config;
use App\Core\Database;
use App\Core\Security;
use RuntimeException;
use Throwable;

/**
 * 站点运营数据统一访问层（docs/站点运营模块设计.md §一）
 *
 * 三条硬约定：
 *   1. 工具页永不投广告（/tool/{id}/use 不调用本类广告方法）
 *   2. 关闭即零痕迹：关闭 / 无代码 / 生效期外 → 方法返回空，模板不输出任何节点
 *   3. 模板不读裸配置：旧键兼容、坏数据降级全部集中在这里
 *
 * 所有外部 URL 出口前过 Security::safeExternalUrl 白名单，非法条目直接丢弃。
 */
final class SiteOps
{
    /** 广告插槽固定键名 */
    public const AD_SLOTS = ['home_top', 'list_top', 'list_bottom', 'detail_side', 'detail_bottom', 'footer'];

    private function __construct()
    {
    }

    // ── 广告位 ──────────────────────────────────────

    /**
     * 取可渲染的广告位数据。不可渲染（关闭 / 无代码 / 生效期外）返回 null。
     *
     * @return array{slot: string, code: string, device: string}|null
     */
    public static function adSlot(string $slot): ?array
    {
        if (!in_array($slot, self::AD_SLOTS, true) || !App::hasDb()) {
            return null;
        }

        try {
            $row = App::db()->fetch('SELECT slot, enabled, code, device, start_date, end_date FROM ad_slots WHERE slot = :slot', [':slot' => $slot]);
        } catch (Throwable) {
            return null;
        }

        if ($row === null || (int) $row['enabled'] !== 1 || trim((string) $row['code']) === '') {
            return null;
        }

        if (!self::inDateWindow((string) ($row['start_date'] ?? ''), (string) ($row['end_date'] ?? ''))) {
            return null;
        }

        $device = in_array($row['device'], ['all', 'desktop', 'mobile'], true) ? (string) $row['device'] : 'all';

        return ['slot' => (string) $row['slot'], 'code' => (string) $row['code'], 'device' => $device];
    }

    // ── 公告 ────────────────────────────────────────

    /**
     * 生效期内、启用的公告（pinned → sort → id 排序）。
     *
     * @return list<array<string, mixed>>
     */
    public static function announcements(): array
    {
        if (!Config::bool('announce_enabled', false) || !App::hasDb()) {
            return [];
        }

        try {
            $rows = App::db()->fetchAll(
                'SELECT id, type, text, link, link_text, pinned FROM announcements
                 WHERE enabled = 1
                   AND (start_date IS NULL OR start_date <= :today)
                   AND (end_date IS NULL OR end_date = \'\' OR end_date >= :today)
                 ORDER BY pinned DESC, sort_order ASC, id DESC',
                [':today' => date('Y-m-d')]
            );
        } catch (Throwable) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            $link = Security::safeExternalUrl((string) ($row['link'] ?? ''));
            $text = trim((string) $row['text']);
            if ($text === '') {
                continue;
            }
            $out[] = [
                'id'        => (int) $row['id'],
                'type'      => in_array($row['type'], ['info', 'update', 'warning'], true) ? (string) $row['type'] : 'info',
                'text'      => $text,
                'link'      => $link,
                'link_text' => trim((string) ($row['link_text'] ?? '')) ?: '查看',
                'finger'    => sha1($text),
            ];
        }

        return $out;
    }

    /**
     * 通栏条数配置（1~3）。
     */
    public static function announceBarCount(): int
    {
        return min(3, max(1, Config::int('announce_bar_count', 1)));
    }

    // ── 友链 ────────────────────────────────────────

    /**
     * 页脚友链（全站页脚常显）：placement home / all，最多 20 条。
     *
     * @return list<array{name: string, url: string, nofollow: bool}>
     */
    public static function friendLinks(): array
    {
        return self::friendLinksBySql(
            "SELECT name, url, nofollow FROM friend_links
             WHERE enabled = 1 AND placement IN ('home', 'all')
             ORDER BY sort_order ASC, id ASC
             LIMIT 20"
        );
    }

    /**
     * 友链独立页：全部启用的友链（不分投放面，最多 100 条）。
     *
     * @return list<array{name: string, url: string, nofollow: bool}>
     */
    public static function allFriendLinks(): array
    {
        return self::friendLinksBySql(
            'SELECT name, url, nofollow FROM friend_links
             WHERE enabled = 1
             ORDER BY sort_order ASC, id ASC
             LIMIT 100'
        );
    }

    /**
     * 友链页是否有内容（页脚「友情链接」入口的显示依据）。
     */
    public static function hasFriendPageContent(): bool
    {
        if (!App::hasDb()) {
            return false;
        }

        try {
            $count = (int) App::db()->fetchColumn('SELECT COUNT(*) FROM friend_links WHERE enabled = 1');
        } catch (Throwable) {
            return false;
        }

        return $count > 0 || trim(Config::string('friend_link_apply_note')) !== '';
    }

    /**
     * 申请友链说明（多行文本，友链页展示；空 = 使用默认提示）。
     */
    public static function friendApplyNote(): string
    {
        $note = trim(Config::string('friend_link_apply_note'));

        return $note !== '' ? $note : '如需与本站交换友链，请通过公众号或站长联系方式洽谈。';
    }

    /**
     * 友链查询公共出口：白名单过滤，非法条目直接丢弃。
     *
     * @return list<array{name: string, url: string, nofollow: bool}>
     */
    private static function friendLinksBySql(string $sql): array
    {
        if (!App::hasDb()) {
            return [];
        }

        try {
            $rows = App::db()->fetchAll($sql);
        } catch (Throwable) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            $name = trim((string) $row['name']);
            $url = Security::safeExternalUrl((string) $row['url']);
            // URL 白名单失败的条目直接跳过（后台标红提示，前台零痕迹）
            if ($name === '' || $url === null) {
                continue;
            }
            $out[] = ['name' => $name, 'url' => $url, 'nofollow' => (int) $row['nofollow'] === 1];
        }

        return $out;
    }

    // ── 导航（顶部 / 页脚快捷导航，均可后台配置）────

    /**
     * 顶部导航菜单。site_config.header_nav = [{label, url}]（JSON）。
     * 未配置 / 全部非法时回退默认两项，保证导航永不为空。
     *
     * @return list<array{label: string, url: string}>
     */
    public static function headerNav(): array
    {
        $items = self::parseNavJson(Config::string('header_nav'));

        return $items !== [] ? $items : [
            ['label' => '首页', 'url' => '/'],
            ['label' => '全部工具', 'url' => '/tools'],
        ];
    }

    /**
     * 页脚快捷导航。未配置返回 []，视图据此回退内置导航。
     *
     * @return list<array{label: string, url: string}>
     */
    public static function footerNav(): array
    {
        return self::parseNavJson(Config::string('footer_nav'));
    }

    /**
     * 解析导航 JSON 并过 URL 白名单，坏条目直接丢弃。
     *
     * @return list<array{label: string, url: string}>
     */
    private static function parseNavJson(string $json): array
    {
        if (trim($json) === '') {
            return [];
        }

        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return [];
        }

        $out = [];
        foreach ($decoded as $item) {
            if (!is_array($item)) {
                continue;
            }
            $label = trim((string) ($item['label'] ?? ''));
            $url = Security::safeExternalUrl((string) ($item['url'] ?? ''));
            if ($label === '' || $url === null) {
                continue;
            }
            $out[] = ['label' => $label, 'url' => $url];
        }

        return $out;
    }

    /**
     * 把「每行 名称 | URL」文本解析为导航 JSON（后台保存用）。
     * 非法行跳过；全部为空返回 ''（= 未配置，走默认）。
     */
    public static function parseNavText(string $text): string
    {
        $lines = preg_split('/\r\n|\r|\n/', $text) ?: [];
        $items = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $parts = array_map('trim', explode('|', $line));
            $label = $parts[0] ?? '';
            $url = Security::safeExternalUrl($parts[1] ?? '');
            if ($label === '' || $url === null) {
                continue;
            }
            $items[] = ['label' => mb_substr($label, 0, 20), 'url' => $url];
        }

        return $items !== [] ? json_encode($items, JSON_UNESCAPED_UNICODE) : '';
    }

    /**
     * 把导航 JSON 反解析回「每行 名称 | URL」文本（后台编辑表单回显用）。
     */
    public static function navToText(string $json): string
    {
        $items = self::parseNavJson($json);
        if ($items === []) {
            return '';
        }

        $lines = [];
        foreach ($items as $item) {
            $lines[] = $item['label'] . ' | ' . $item['url'];
        }

        return implode("\n", $lines);
    }

    // ── 页脚 ────────────────────────────────────────

    /**
     * 归一化的页脚配置。
     *
     * @return array{brand_desc: string, copyright: string, icp_number: string, icp_url: ?string,
     *               police_number: string, police_url: ?string, statement: string}
     */
    public static function footer(): array
    {
        $copyright = trim(Config::string('footer_copyright'));
        if ($copyright === '') {
            $copyright = '© {year} {site_name}';
        }
        $copyright = str_replace(
            ['{year}', '{site_name}'],
            [date('Y'), site_name()],
            $copyright
        );

        $icpUrl = Security::safeExternalUrl(trim(Config::string('footer_icp_url')));
        $policeUrl = Security::safeExternalUrl(trim(Config::string('footer_police_url')));

        return [
            'brand_desc'    => trim(Config::string('footer_brand_desc')) ?: site_description(),
            'copyright'     => $copyright,
            'icp_number'    => trim(Config::string('footer_icp_number')),
            'icp_url'       => $icpUrl,
            'police_number' => trim(Config::string('footer_police_number')),
            'police_url'    => $policeUrl,
            'statement'     => trim(Config::string('footer_statement')),
        ];
    }

    // ── 赞助 ────────────────────────────────────────

    public static function sponsorEnabled(): bool
    {
        return Config::bool('sponsor_enabled', false);
    }

    /**
     * 归一化的赞助配置（含白名单过滤后的收款码地址）。
     *
     * @return array{title: string, desc: string, cost_note: string, note: string,
     *               wechat_qr: ?string, alipay_qr: ?string, show_footer: bool}|null
     */
    public static function sponsor(): ?array
    {
        if (!self::sponsorEnabled()) {
            return null;
        }

        return [
            'title'     => trim(Config::string('sponsor_title')) ?: '支持课工具',
            'desc'      => trim(Config::string('sponsor_desc')),
            'cost_note' => trim(Config::string('sponsor_cost_note')),
            'note'      => trim(Config::string('sponsor_note')) ?: '赞助完全自愿，不赞助不影响任何功能的使用。',
            'wechat_qr' => Security::safeExternalUrl(trim(Config::string('sponsor_wechat_qr'))),
            'alipay_qr' => Security::safeExternalUrl(trim(Config::string('sponsor_alipay_qr'))),
            'show_footer' => Config::bool('sponsor_show_footer', true),
        ];
    }

    /**
     * 鸣谢列表（时间倒序）。
     *
     * @return list<array<string, mixed>>
     */
    public static function sponsorThanks(): array
    {
        if (!App::hasDb()) {
            return [];
        }

        try {
            $rows = App::db()->fetchAll(
                'SELECT id, name, note, amount, created_at FROM sponsor_thanks ORDER BY id DESC LIMIT 50'
            );
        } catch (Throwable) {
            return [];
        }

        return $rows;
    }

    // ── 日期窗口 ────────────────────────────────────

    /**
     * 日期字符串比较判定生效期（避免时区歧义，edupick 结论）。
     */
    public static function inDateWindow(string $start, string $end): bool
    {
        $today = date('Y-m-d');
        if ($start !== '' && $today < $start) {
            return false;
        }
        if ($end !== '' && $today > $end) {
            return false;
        }

        return true;
    }
}
