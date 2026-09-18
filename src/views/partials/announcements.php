<?php use App\Core\Config;
use App\Core\View;

/**
 * 公告通栏 + 公告中心弹层（零痕迹约定：关闭时不输出任何节点）
 *
 * @var list<array<string, mixed>> $announcements SiteOps::announcements()
 * @var int $barCount 通栏条数
 * @var bool $closable 是否可关闭
 * @var bool $centered 通栏文案居中
 */
if ($announcements === []) {
    return;
}
$bar = array_slice($announcements, 0, $barCount);
$typeIcon = ['info' => 'info', 'update' => 'check', 'warning' => 'alert-triangle'];
?><div class="notice-bar<?= $centered ? ' notice-bar--center' : '' ?>" data-closable="<?= $closable ? '1' : '0' ?>">
  <div class="container notice-bar-inner">
    <ul class="notice-list">
      <?php foreach ($bar as $item): ?>
        <li class="notice-item notice-item--<?= e($item['type']) ?>" data-notice-finger="<?= e($item['finger']) ?>">
          <span class="notice-dot" aria-hidden="true"></span>
          <span class="notice-text"><?= e($item['text']) ?></span>
          <?php if ($item['link'] !== null): ?>
            <a class="notice-link" href="<?= e($item['link']) ?>" <?= str_starts_with($item['link'], '/') ? '' : 'target="_blank" rel="noopener nofollow"' ?>><?= e($item['link_text']) ?></a>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>
    <div class="notice-actions">
      <button class="notice-center-btn" type="button" data-notice-center>公告中心</button>
      <?php if ($closable): ?>
        <button class="notice-close" type="button" aria-label="关闭公告">&times;</button>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="notice-modal" id="notice-center" style="display:none;">
  <div class="notice-modal-box">
    <div class="notice-modal-head">
      <h2 class="notice-modal-title">公告中心</h2>
      <button class="notice-modal-close" type="button" aria-label="关闭">&times;</button>
    </div>
    <ul class="notice-modal-list">
      <?php foreach ($announcements as $item): ?>
        <li class="notice-item notice-item--<?= e($item['type']) ?>">
          <span class="notice-dot" aria-hidden="true"></span>
          <div>
            <span class="notice-text"><?= e($item['text']) ?></span>
            <?php if ($item['link'] !== null): ?>
              <a class="notice-link" href="<?= e($item['link']) ?>" <?= str_starts_with($item['link'], '/') ? '' : 'target="_blank" rel="noopener nofollow"' ?>><?= e($item['link_text']) ?></a>
            <?php endif; ?>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
</div>
