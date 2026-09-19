<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\App;
use App\Core\Config;
use App\Core\Security;
use RuntimeException;

/**
 * 离线导航门户渲染器（P3）
 *
 * 离线包内的 index.html 与在线版**共用同一套视觉外壳**（同一 bundle CSS 源 +
 * 相同的 .site-header / .tool-grid / .card-tool / .site-footer 类名），
 * 由本类独立渲染为单文件：CSS / 图标 sprite / 数据 / JS 全部内联，
 * file:// 双击可用且零网络请求（tasks/P3-离线包.md §三）。
 *
 * 关键约束：
 *  - 不复用 layout/site.php（其引用外部 CSS/JS 与服务器 URL）；
 *    本类是「独立外壳模板」，在线视觉源自同一 assets-src CSS 层，禁止复制分叉样式
 *  - 数据内嵌 <script>（file:// 下 fetch 本地 JSON 会被浏览器 CORS 拦截），
 *    tools-index.json 仅作为数据附赠文件随包分发
 *  - 门户 JS 走 ES5（老教室浏览器兼容），不使用 let/const/箭头函数/模板字符串
 *  - 所有指回站点的链接带 UTM ?from=offline-pkg（品牌回流主力触点）
 */
final class PortalRenderer
{
    public function render(array $tools, string $pkgTitle, string $slug, string $version): string
    {
        $siteName = site_name();
        $siteUrl = site_url();
        $year = date('Y');

        $bundle = $this->readFile(App::path('public/assets/css/site.bundle.css'));
        $portal = $this->readFile(App::path('assets-src/css/portal.css'));
        $sprite = $this->readFile(App::path('public/assets/icons/sprite.svg'));

        $dataJson = json_encode(
            ['pkg' => ['title' => $pkgTitle, 'slug' => $slug, 'version' => $version], 'tools' => $tools],
            JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        );
        if ($dataJson === false) {
            throw new RuntimeException('门户数据序列化失败');
        }

        $esc = static fn (mixed $v): string => Security::escape((string) $v);

        // 站点外链（离线环境的品牌回流触点，一律带 UTM）
        if ($siteUrl !== '') {
            $homeUrl = $siteUrl . '/?from=offline-pkg';
            $checkBtn = '<a class="btn btn-ghost btn-sm" href="' . $esc($homeUrl)
                . '" target="_blank" rel="noopener">' . $this->icon('external-link') . '检查更新</a>';
            $footerUpdate = '<a href="' . $esc($homeUrl) . '" target="_blank" rel="noopener">'
                . '访问在线站点获取最新工具</a>';
        } else {
            $checkBtn = '';
            $footerUpdate = '<span>部署站点后重新打包即可获得「检查更新」链接</span>';
        }

        // 站点二维码（站长在站点设置配置的 community_qr_image）。
        // 打包时取图转 data URI 内嵌——file:// 下外链图片即外部请求，禁止。
        $qrBlock = '';
        $qrDataUri = $this->qrDataUri();
        if ($qrDataUri !== null) {
            $qrText = trim(Config::string('community_qr_text'));
            if ($qrText === '') {
                // 与前台详情页 / 网盘中间页的默认引导文案保持一致
                $qrText = '扫码关注，新工具上线第一时间通知';
            }
            $qrBlock = '<div class="portal-qr">'
                . '<img src="' . $esc($qrDataUri) . '" alt="站点二维码" width="96" height="96">'
                . '<span>' . $esc($qrText) . '</span>'
                . '</div>';
        }

        $toolsJson = $dataJson; // 整包注入，前端解析 window.ET_PORTAL

        $html = <<<HTML
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{$esc($pkgTitle)} — {$esc($siteName)} 离线合集</title>
<meta name="robots" content="noindex">
<style>
{$bundle}
{$portal}
</style>
</head>
<body>
<div class="site">
  <a class="skip-link" href="#main">跳到主要内容</a>

  <div style="display:none">{$sprite}</div>

  <header class="site-header">
    <div class="container site-header-inner">
      <a class="site-logo" href="index.html">
        <span class="site-logo-mark">{$this->icon('grid-2x2')}</span>
        <span>{$esc($siteName)}</span>
      </a>
      <nav class="site-nav" aria-label="合集信息">
        <span class="tag tag-info">{$this->icon('wifi-off')}离线合集 v{$esc($version)}</span>
      </nav>
      <div class="site-header-actions">{$checkBtn}</div>
    </div>
  </header>

  <main class="site-main" id="main">
    <div class="container">
      <section class="hero">
        <h1 class="hero-title">{$esc($siteName)}<span class="hero-title-cn">课工具</span></h1>
        <p class="hero-subtitle">离线合集包 · 解压即用 · 无需安装 · 断网也能用</p>
        <div class="portal-toolbar">
          <label class="visually-hidden" for="et-q">搜索工具</label>
          <input class="form-input" type="search" id="et-q" placeholder="搜索工具名称、学科或用途…"
                 autocomplete="off" maxlength="50">
          <label class="visually-hidden" for="et-stage">按学段筛选</label>
          <select class="form-input" id="et-stage">
            <option value="all">全部学段</option>
            <option value="小学">小学</option>
            <option value="初中">初中</option>
            <option value="高中">高中</option>
            <option value="通用">通用</option>
          </select>
          <label class="visually-hidden" for="et-subject">按学科筛选</label>
          <select class="form-input" id="et-subject">
            <option value="all">全部学科</option>
          </select>
        </div>
      </section>

      <section class="section">
        <div class="section-head">
          <h2 class="section-title">全部工具</h2>
          <span class="section-more" id="et-count"></span>
        </div>
        <div class="tool-grid" id="et-grid"></div>
        <div class="empty" id="et-empty" style="display:none">
          <div class="empty-title">没有符合条件的工具</div>
          <div>换个关键词或清除筛选试试。</div>
        </div>
      </section>
    </div>
  </main>

  <footer class="site-footer">
    <div class="container">
      <div class="site-footer-bottom portal-footer-bottom">
        <p class="portal-footer-note">本合集由 {$esc($siteName)} 生成，打包于 {$esc(date('Y-m-d'))}。
            {$footerUpdate}</p>
        {$qrBlock}
        <p class="site-footer-copyright">© {$esc($year)} {$esc($siteName)}</p>
      </div>
    </div>
  </footer>
</div>

<script>window.ET_PORTAL = {$toolsJson};</script>
<script>
HTML
            . <<<'JS'
(function () {
    'use strict';

    var conf = window.ET_PORTAL || {};
    var tools = conf.tools || [];
    var TYPE_LABEL = { 'fixed': '知识速查', 'shell': '课堂互动', 'experiment': '实验模拟' };
    var TYPE_ICON = { 'fixed': 'book-open', 'shell': 'play', 'experiment': 'flask-conical' };

    function el(id) { return document.getElementById(id); }
    function esc(s) {
        return String(s === null || s === undefined ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }
    function icon(name) {
        return '<svg class="icon" aria-hidden="true" focusable="false">'
            + '<use href="#i-' + name + '" xlink:href="#i-' + name + '"></use></svg>';
    }

    // grade_range 数组 → 学段标签（与在线版 grade_range_label 同口径）
    function stageOf(grades) {
        if (!grades || !grades.length) { return '通用'; }
        var has = {}, i;
        for (i = 0; i < grades.length; i++) { has[grades[i]] = true; }
        if (has['1-12']) { return '全学段'; }
        var out = [];
        if (has['1-6'] || has['1-2'] || has['3-4'] || has['5-6']) { out.push('小学'); }
        if (has['7-9']) { out.push('初中'); }
        if (has['10-12']) { out.push('高中'); }
        return out.length ? out.join('·') : '通用';
    }
    function matchStage(label, want) {
        if (want === 'all') { return true; }
        if (want === '通用') { return label === '通用' || label === '全学段'; }
        return label.indexOf(want) !== -1;
    }

    // 聚合学科下拉选项
    var seen = {}, subjectNames = [];
    for (var t = 0; t < tools.length; t++) {
        var subs = tools[t].subjects || [];
        for (var s = 0; s < subs.length; s++) {
            if (!seen[subs[s]]) { seen[subs[s]] = true; subjectNames.push(subs[s]); }
        }
    }
    subjectNames.sort();
    var subjectSel = el('et-subject');
    for (var o = 0; o < subjectNames.length; o++) {
        var opt = document.createElement('option');
        opt.value = subjectNames[o];
        opt.appendChild(document.createTextNode(subjectNames[o]));
        subjectSel.appendChild(opt);
    }

    var state = { q: '', stage: 'all', subject: 'all' };

    function card(t) {
        var type = t.type || 'shell';
        var tags = (t.subjects || []).slice(0, 2);
        var tagsHtml = '';
        for (var i = 0; i < tags.length; i++) {
            tagsHtml += '<span class="tag">' + esc(tags[i]) + '</span>';
        }
        return '<a class="card card-tool" href="' + esc(t.file) + '" target="_blank">'
            + '<div class="card-body">'
            + '<div class="card-tool-top">'
            + '<span class="card-tool-icon">' + icon(TYPE_ICON[type] || 'play') + '</span>'
            + '<span class="tag tag-info">' + (TYPE_LABEL[type] || '课堂互动') + '</span>'
            + '</div>'
            + '<h3 class="card-tool-title">' + esc(t.title) + '</h3>'
            + '<p class="card-tool-desc">' + esc(t.description) + '</p>'
            + '<div class="card-tool-meta">'
            + '<span class="tag-group">' + tagsHtml + '</span>'
            + '<span class="card-tool-version">v' + esc(t.version) + '</span>'
            + '</div>'
            + '</div>'
            + '</a>';
    }

    function render() {
        var html = '', shown = 0;
        for (var i = 0; i < tools.length; i++) {
            var t = tools[i];
            if (state.q !== '') {
                var hay = (t.title + ' ' + t.description + ' ' + (t.tags || []).join(' ')
                    + ' ' + (t.subjects || []).join(' ')).toLowerCase();
                if (hay.indexOf(state.q) === -1) { continue; }
            }
            if (!matchStage(stageOf(t.grade_range), state.stage)) { continue; }
            if (state.subject !== 'all' && (t.subjects || []).indexOf(state.subject) === -1) { continue; }
            html += card(t);
            shown++;
        }
        el('et-grid').innerHTML = html;
        el('et-count').innerHTML = shown + ' / ' + tools.length + ' 个工具';
        el('et-empty').style.display = shown === 0 ? '' : 'none';
    }

    el('et-q').addEventListener('input', function () { state.q = this.value.toLowerCase(); render(); });
    el('et-stage').addEventListener('change', function () { state.stage = this.value; render(); });
    el('et-subject').addEventListener('change', function () { state.subject = this.value; render(); });

    render();
})();
JS
            . <<<'HTML'
</script>
</body>
</html>
HTML;

        return $html;
    }

    private function icon(string $name): string
    {
        return '<svg class="icon" aria-hidden="true" focusable="false">'
            . '<use href="#i-' . Security::escape($name) . '" xlink:href="#i-' . Security::escape($name) . '"></use></svg>';
    }

    /**
     * 站点二维码 data URI（打包时快照，file:// 离线可用）。
     *
     * 来源：站点设置 community_qr_image（与前台公众号二维码同一配置）。
     * 支持 http(s) URL（打包机联网拉取，5 秒超时）/ 站内路径（读 public 文件）
     * / data: URI（直接透传）。任何失败返回 null，门户降级为无二维码——
     * 二维码是加分项，绝不能让它阻断打包。
     */
    public function qrDataUri(): ?string
    {
        $source = trim(Config::string('community_qr_image'));
        if ($source === '') {
            return null;
        }

        // 已是 data URI：直接透传
        if (preg_match('#^data:image/(png|jpe?g|webp);base64,#i', $source) === 1) {
            return $source;
        }

        $bytes = null;
        if (preg_match('#^https?://#i', $source) === 1) {
            $context = stream_context_create(['http' => ['timeout' => 5, 'follow_location' => 1]]);
            $bytes = @file_get_contents($source, false, $context);
        } elseif (str_starts_with($source, '/')) {
            // 站内相对路径：限制在 public/ 内（防穿越）
            try {
                $file = Security::safePath(App::path('public'), ltrim($source, '/'));
                $bytes = is_file($file) ? (string) file_get_contents($file) : null;
            } catch (RuntimeException) {
                return null;
            }
        }

        if ($bytes === false || $bytes === null || strlen($bytes) > 512 * 1024) {
            return null;
        }

        // 嗅探真实图片格式，避免信任 URL 后缀
        $mime = match (substr($bytes, 0, 4)) {
            "\x89PNG" => 'image/png',
            "\xFF\xD8\xFF" => 'image/jpeg',
            'RIFF' => str_starts_with(substr($bytes, 8, 4), 'WEBP') ? 'image/webp' : null,
            default => null,
        };
        if ($mime === null) {
            return null;
        }

        return 'data:' . $mime . ';base64,' . base64_encode($bytes);
    }

    private function readFile(string $path): string
    {
        $content = @file_get_contents($path);
        if ($content === false || $content === '') {
            throw new RuntimeException('门户资源缺失: ' . basename($path) . '（请先运行构建脚本）');
        }

        return $content;
    }
}
