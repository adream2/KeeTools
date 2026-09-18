<?php
use App\Core\View;
use App\Services\SiteOps;

/**
 * 搜索页 / 全部工具页
 *
 * @var string $query 关键词（空 = 全部工具）
 * @var list<array<string, mixed>> $tools
 * @var int $total
 */
?><div class="page-head">
  <h1 class="page-title">
    <?= $query !== '' ? '搜索：' . e($query) : '全部工具' ?>
  </h1>
  <p class="page-desc">
    找到 <strong><?= e((string) $total) ?></strong> 个工具
    <?php if ($query === ''): ?>，全部免费、单文件即开即用<?php endif; ?>
  </p>
</div>

<form class="form-search page-search" action="<?= e(url('/search')) ?>" method="get" role="search">
  <label class="visually-hidden" for="page-q">搜索工具</label>
  <input class="form-input" type="search" id="page-q" name="q" value="<?= e($query) ?>"
         placeholder="搜索工具名称、学科或用途…" autocomplete="off" maxlength="50">
  <button class="btn btn-primary" type="submit"><?= icon('search') ?>搜索</button>
</form>

<?php View::include('partials/ad-slot', ['slot' => SiteOps::adSlot('list_top'), 'position' => 'top']); ?>

<?php if ($tools !== []): ?>
  <div class="tool-grid section">
    <?php foreach ($tools as $tool): ?>
      <?php View::include('partials/tool-card', ['tool' => $tool]); ?>
    <?php endforeach; ?>
  </div>
<?php else: ?>
  <div class="empty">
    <div class="empty-title">没有找到与「<?= e($query) ?>」相关的工具</div>
    <div>试试「点名」「倒计时」等关键词，或浏览<a href="<?= e(url('/tools')) ?>">全部工具</a>。</div>
  </div>
<?php endif; ?>

<?php View::include('partials/ad-slot', ['slot' => SiteOps::adSlot('list_bottom'), 'position' => 'bottom']); ?>
