<?php use App\Core\Config;
use App\Core\View;
use App\Services\SiteOps;

/**
 * 工具详情页（转化页）
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

// 主按钮：合集包（网盘入口，最高视觉权重）
$primaryNetdisk = $netdisks[0] ?? null;

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
    <div class="tag-group">
      <span class="tag <?= e(tool_type_tag_class($type)) ?>"><?= e(tool_type_label($type)) ?></span>
      <span class="tag">v<?= e((string) $tool['version']) ?></span>
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
    <div class="card tool-get-card">
      <div class="card-body">
        <?php if ($primaryNetdisk !== null): ?>
          <a class="btn btn-primary btn-lg btn-block" href="<?= e(url('/netdisk/' . rawurlencode($toolId) . '?type=' . rawurlencode((string) $primaryNetdisk['netdisk_type']))) ?>">
            <?= icon('cloud-download') ?>下载合集包（<?= e((string) $primaryNetdisk['type_label']) ?>）
          </a>
          <?php if (count($netdisks) > 1): ?>
            <div class="tool-get-alt">
              其他网盘：
              <?php foreach (array_slice($netdisks, 1) as $item): ?>
                <a class="tag" href="<?= e(url('/netdisk/' . rawurlencode($toolId) . '?type=' . rawurlencode((string) $item['netdisk_type']))) ?>">
                  <?= e((string) $item['type_label']) ?>
                </a>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        <?php else: ?>
          <span class="btn btn-primary btn-lg btn-block is-disabled" aria-disabled="true">
            <?= icon('clock') ?>合集包整理中，敬请期待
          </span>
        <?php endif; ?>

        <p class="tool-get-note">
          合集包含本地导航门户，一次下载，全站工具离线使用。
        </p>

        <?php if ($previewEnabled): ?>
          <div class="tool-preview" data-lazy-iframe="<?= e(url('/tool/' . rawurlencode($toolId) . '/use')) ?>">
            <div class="tool-preview-skeleton">
              <span class="tool-preview-hint"><?= icon('play') ?>点击加载在线预览</span>
            </div>
          </div>
          <noscript>
            <iframe class="tool-preview-frame" src="<?= e(url('/tool/' . rawurlencode($toolId) . '/use')) ?>"
                    title="在线预览" loading="lazy"></iframe>
          </noscript>
        <?php endif; ?>

        <?php if ($downloadEnabled && $previewEnabled): ?>
          <p class="tool-download-minor">
            先下载一个试试：<a href="<?= e(url('/download/' . rawurlencode($toolId))) ?>" data-track-download="<?= e($toolId) ?>">仅下载本工具（单文件）</a>
          </p>
        <?php endif; ?>
      </div>
    </div>

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
          <div class="tool-meta-row">
            <span class="tool-meta-label">更新于</span>
            <span class="tool-meta-value tool-meta-mono"><?= e((string) $tool['updated_at']) ?></span>
          </div>
        </div>
      </div>
    </div>

    <?php View::include('partials/ad-slot', ['slot' => SiteOps::adSlot('detail_side'), 'position' => 'side']); ?>
  </div>
</div>

<script type="application/ld+json"><?= json_encode($jsonLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>

<?php if ($related !== []): ?>
<section class="section">
  <div class="section-head">
    <h2 class="section-title">相关工具</h2>
  </div>
  <div class="tool-grid">
    <?php foreach ($related as $relatedTool): ?>
      <?php View::include('partials/tool-card', ['tool' => $relatedTool]); ?>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>
