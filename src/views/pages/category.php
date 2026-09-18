<?php
use App\Core\View;
use App\Services\SiteOps;

/**
 * 分类页（一级学段 / 二级学科）
 *
 * @var array<string, mixed> $category
 * @var array{name: string, slug: string}|null $parent 二级学科页的所属学段
 * @var list<array{name: string, slug: string, count: int}> $chips
 *     一级学段页 = 下属学科；二级学科页 = 同学段兄弟学科
 * @var list<array<string, mixed>> $tools
 * @var int $total
 */
$isActive = static fn (string $slug): bool => $slug === $category['slug'];
?><nav class="breadcrumb" aria-label="面包屑">
  <a href="<?= e(url('/')) ?>">首页</a>
  <span class="breadcrumb-sep">/</span>
  <?php if ($parent !== null): ?>
    <a href="<?= e(url('/category/' . rawurlencode($parent['slug']))) ?>"><?= e($parent['name']) ?></a>
    <span class="breadcrumb-sep">/</span>
  <?php endif; ?>
  <span><?= e((string) $category['name']) ?></span>
</nav>

<div class="page-head">
  <h1 class="page-title">
    <?php if ($category['icon'] !== null): ?>
      <?= icon((string) $category['icon'], 'page-title-icon') ?>
    <?php endif; ?>
    <?= e((string) $category['name']) ?>
  </h1>
  <p class="page-desc">共 <strong><?= e((string) $total) ?></strong> 个免费课堂工具</p>
</div>

<?php View::include('partials/ad-slot', ['slot' => SiteOps::adSlot('list_top'), 'position' => 'top']); ?>

<?php if ($chips !== []): ?>
<div class="chip-row">
  <?php foreach ($chips as $chip): ?>
    <a class="filter-chip<?= $isActive($chip['slug']) ? ' is-active' : '' ?>"
       href="<?= e(url('/category/' . rawurlencode($chip['slug']))) ?>">
      <?= e($chip['name']) ?><?= $chip['count'] > 0 ? ' ' . e((string) $chip['count']) : '' ?>
    </a>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if ($tools !== []): ?>
  <div class="tool-grid">
    <?php foreach ($tools as $tool): ?>
      <?php View::include('partials/tool-card', ['tool' => $tool]); ?>
    <?php endforeach; ?>
  </div>
<?php else: ?>
  <div class="empty">
    <div class="empty-title">该分类下还没有工具</div>
    <div>可以先看看<a href="<?= e(url('/tools')) ?>">全部工具</a>，或回到<a href="<?= e(url('/')) ?>">首页</a>按学段浏览。</div>
  </div>
<?php endif; ?>

<?php View::include('partials/ad-slot', ['slot' => SiteOps::adSlot('list_bottom'), 'position' => 'bottom']); ?>
