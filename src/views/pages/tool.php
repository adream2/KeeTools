<?php use App\Core\Config;
use App\Core\View;
use App\Services\SiteOps;

/**
 * 工具详情页（转化页，参考 edupick 布局）
 *
 * 主栏：工具头 → 在线预览（直接 iframe，工具条含全屏/新窗口）→ 介绍
 * 侧栏：下载卡（网盘按钮列表）→ 公众号 → 工具信息 → 同类推荐
 *
 * @var array<string, mixed> $tool
 * @var array{name: string, slug: string}|null $stage 工具所属学段（面包屑）
 * @var list<array<string, mixed>> $related 同 family 相关工具
 * @var list<array<string, mixed>> $netdisks 活跃网盘链接
 */
$subjects = is_array($tool['subjects'] ?? null) ? $tool['subjects'] : [];
$grades = is_array($tool['grade_range'] ?? null) ? $tool['grade_range'] : [];
$tags = is_array($tool['tags'] ?? null) ? $tool['tags'] : [];
$type = (string) ($tool['type'] ?? 'shell');
$toolId = (string) $tool['tool_id'];
$downloadEnabled = Config::bool('DOWNLOAD_DIRECT_ENABLED', true);
$previewEnabled = (bool) $tool['single_file'];
$useUrl = url('/tool/' . rawurlencode($toolId) . '/use');
$communityQr = trim(Config::string('community_qr_image'));
$communityText = trim(Config::string('community_qr_text'));

// 工具定制需求入口（后台「赞助」Tab 配置；未配置时返回 null，页面零痕迹）
$customEntry = SiteOps::customToolEntry();

// 结构化数据（SEO）
$jsonLd = [
    '@context'    => 'https://schema.org', // et-allow-external（JSON-LD 结构化数据词汇表标识，非资源加载）
    '@type'       => 'SoftwareApplication',
    'name'        => $tool['title'],
    'description' => $tool['description'],
    'softwareVersion' => $tool['version'],
    'applicationCategory' => 'EducationalApplication',
    'operatingSystem' => 'Web, Windows, macOS',
    'offers'      => ['@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'CNY'],
    'author'      => ['@type' => 'Person', 'name' => $tool['author'] !== '' ? $tool['author'] : site_name()],
];
?><nav class="breadcrumb" aria-label="面包屑">
  <a href="<?= e(url('/')) ?>">首页</a>
  <span class="breadcrumb-sep">/</span>
  <?php if ($stage !== null): ?>
    <a href="<?= e(url('/category/' . rawurlencode($stage['slug']))) ?>"><?= e($stage['name']) ?></a>
    <span class="breadcrumb-sep">/</span>
  <?php else: ?>
    <a href="<?= e(url('/tools')) ?>">全部工具</a>
    <span class="breadcrumb-sep">/</span>
  <?php endif; ?>
  <span><?= e((string) $tool['title']) ?></span>
</nav>

<div class="tool-hero">
  <span class="tool-hero-icon"><?= icon(tool_type_icon($type)) ?></span>
  <div class="tool-hero-main">
    <h1 class="tool-hero-title"><?= e((string) $tool['title']) ?></h1>
    <p class="tool-hero-desc"><?= e((string) $tool['description']) ?></p>
    <div class="tag-group">
      <span class="tag <?= e(tool_type_tag_class($type)) ?>"><?= e(tool_type_label($type)) ?></span>
      <span class="tag">v<?= e((string) $tool['version']) ?></span>
      <span class="tag">更新于 <?= e((string) $tool['updated_at']) ?></span>
      <?php if ($tool['offline']): ?>
        <span class="tag tag-success"><?= icon('wifi-off') ?>离线可用</span>
      <?php endif; ?>
      <?php if ($tool['single_file']): ?>
        <span class="tag tag-info">单文件</span>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="tool-detail">
  <div class="tool-main">
    <?php if ($previewEnabled): ?>
      <section class="tool-preview">
        <div class="preview-bar">
          <span class="preview-title"><span class="preview-dot" aria-hidden="true"></span>在线体验 · 免安装</span>
          <span class="preview-actions">
            <button class="btn btn-sm" type="button" data-fullscreen-target=".tool-preview"><?= icon('maximize-2') ?>全屏体验</button>
            <a class="btn btn-sm" href="<?= e($useUrl) ?>" target="_blank" rel="noopener"><?= icon('external-link') ?>新窗口打开</a>
          </span>
        </div>
        <iframe class="preview-frame" src="<?= e($useUrl) ?>" title="<?= e((string) $tool['title']) ?> 在线体验"
                loading="lazy" allowfullscreen allow="fullscreen"></iframe>
        <p class="preview-tip">
          上方画面即是工具本身，点击后即可直接操作；教室没网时请使用右侧离线合集包。
        </p>
      </section>
    <?php else: ?>
      <div class="alert alert-info">
        <?= icon('package') ?>
        <div>该工具由多个文件组成，暂不支持浏览器内直接体验，请通过右侧离线合集包获取完整文件。</div>
      </div>
    <?php endif; ?>

    <div class="card">
      <div class="card-head">
        <h2 class="card-title">工具介绍</h2>
      </div>
      <div class="card-body">
        <p class="tool-about"><?= e((string) $tool['description']) ?></p>

        <?php if ($tags !== []): ?>
          <div class="tag-group tool-tags">
            <?php foreach ($tags as $tag): ?>
              <a class="tag" href="<?= e(url('/search?q=' . rawurlencode((string) $tag))) ?>"><?= e((string) $tag) ?></a>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <?php View::include('partials/ad-slot', ['slot' => SiteOps::adSlot('detail_bottom'), 'position' => 'bottom']); ?>
  </div>

  <div class="tool-side">
    <div class="card tool-side-download">
      <div class="card-head">
        <h2 class="card-title"><?= icon('cloud-download') ?>下载离线合集包</h2>
      </div>
      <div class="card-body">
        <?php if ($netdisks !== []): ?>
          <div class="pan-list">
            <?php foreach ($netdisks as $i => $item): ?>
              <a class="pan-btn<?= $i === 0 ? ' pan-btn--primary' : '' ?>"
                 href="<?= e(url('/netdisk/' . rawurlencode($toolId) . '?type=' . rawurlencode((string) $item['netdisk_type']))) ?>">
                <span class="pan-btn-name">
                  <?= e((string) $item['type_label']) ?>
                  <?php if ($i === 0): ?><span class="tag tag-success">推荐</span><?php endif; ?>
                </span>
                <span class="pan-btn-go">获取 →</span>
              </a>
            <?php endforeach; ?>
          </div>
          <p class="tool-get-note">建议优先使用主推网盘，速度更稳定；提取码在下一步页面展示。</p>
        <?php else: ?>
          <span class="pan-btn pan-btn--disabled" aria-disabled="true">
            <span class="pan-btn-name">离线包整理中…</span>
          </span>
          <p class="tool-get-note">可先在上方「在线体验」中直接使用，离线包上线后即可下载。</p>
        <?php endif; ?>

        <?php if ($downloadEnabled && $previewEnabled): ?>
          <p class="tool-download-minor">
            先下载一个试试：<a href="<?= e(url('/download/' . rawurlencode($toolId))) ?>">仅下载本工具（单文件）</a>
          </p>
        <?php endif; ?>
      </div>
    </div>

    <?php if ($communityQr !== ''): ?>
      <div class="card">
        <div class="card-body" style="text-align:center;">
          <img src="<?= e($communityQr) ?>" alt="公众号二维码" loading="lazy" referrerpolicy="no-referrer"
               style="width: calc(var(--sp-16) * 2); height: calc(var(--sp-16) * 2); border-radius: var(--r-md); border: var(--bw-1) solid var(--c-border); object-fit: cover;">
          <p class="tool-get-note" style="margin-top: var(--sp-2);">
            <?= e($communityText !== '' ? $communityText : '扫码关注，新工具上线第一时间通知') ?>
          </p>
        </div>
      </div>
    <?php endif; ?>

    <?php if ($customEntry !== null): ?>
      <div class="card">
        <div class="card-head">
          <h2 class="card-title"><?= icon('wrench') ?>工具定制</h2>
        </div>
        <div class="card-body">
          <p class="tool-get-note" style="margin: 0 0 var(--sp-3);"><?= e($customEntry['note']) ?></p>
          <?php if ($customEntry['url'] !== null): ?>
            <a class="btn btn-primary" style="width: 100%; justify-content: center;"
               href="<?= e($customEntry['url']) ?>" target="_blank" rel="noopener">
              <?= icon('external-link') ?>说说我的需求
            </a>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>

    <?php View::include('partials/ad-slot', ['slot' => SiteOps::adSlot('detail_side'), 'position' => 'side']); ?>

    <div class="card">
      <div class="card-head">
        <h2 class="card-title">工具信息</h2>
      </div>
      <div class="card-body">
        <div class="tool-meta-list">
          <div class="tool-meta-row">
            <span class="tool-meta-label">版本</span>
            <span class="tool-meta-value tool-meta-mono">v<?= e((string) $tool['version']) ?></span>
          </div>
          <div class="tool-meta-row">
            <span class="tool-meta-label">类型</span>
            <span class="tool-meta-value"><?= e(tool_type_label($type)) ?></span>
          </div>
          <div class="tool-meta-row">
            <span class="tool-meta-label">作者</span>
            <span class="tool-meta-value"><?= e((string) $tool['author']) ?></span>
          </div>
          <div class="tool-meta-row">
            <span class="tool-meta-label">适用学段</span>
            <span class="tool-meta-value"><?= e(grade_range_label($grades)) ?></span>
          </div>
          <?php if ($subjects !== []): ?>
            <div class="tool-meta-row">
              <span class="tool-meta-label">适用学科</span>
              <span class="tool-meta-value"><?= e(implode('、', $subjects)) ?></span>
            </div>
          <?php endif; ?>
          <div class="tool-meta-row">
            <span class="tool-meta-label">许可证</span>
            <span class="tool-meta-value"><?= $tool['license'] === 'free' ? '免费使用' : e((string) $tool['license']) ?></span>
          </div>
        </div>
      </div>
    </div>

    <?php if ($related !== []): ?>
      <div class="card">
        <div class="card-head">
          <h2 class="card-title">同类推荐</h2>
        </div>
        <div class="card-body">
          <ul class="side-related">
            <?php foreach ($related as $relatedTool): ?>
              <li>
                <a href="<?= e(url('/tool/' . rawurlencode((string) $relatedTool['tool_id']))) ?>">
                  <span class="side-related-icon"><?= icon(tool_type_icon((string) $relatedTool['type'])) ?></span>
                  <span class="side-related-text">
                    <strong><?= e((string) $relatedTool['title']) ?></strong>
                    <em><?= e(mb_substr((string) $relatedTool['description'], 0, 30)) ?></em>
                  </span>
                </a>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>

<script type="application/ld+json"><?= json_encode($jsonLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
