<?php

/**
 * 友链独立页
 *
 * @var list<array{name: string, url: string, nofollow: bool}> $links
 * @var string $applyNote
 */
?><nav class="breadcrumb" aria-label="面包屑">
  <a href="<?= e(url('/')) ?>">首页</a>
  <span class="breadcrumb-sep">/</span>
  <span>友情链接</span>
</nav>

<div class="page-head">
  <h1 class="page-title">友情链接</h1>
  <p class="page-desc">本站合作伙伴与同行站点</p>
</div>

<?php if ($links !== []): ?>
  <div class="friend-grid">
    <?php foreach ($links as $link): ?>
      <a class="card friend-card" href="<?= e($link['url']) ?>"
         <?= str_starts_with($link['url'], '/') ? '' : 'target="_blank" rel="noopener' . ($link['nofollow'] ? ' nofollow' : '') . '"' ?>>
        <span class="friend-card-icon"><?= icon('link') ?></span>
        <span class="friend-card-body">
          <strong><?= e($link['name']) ?></strong>
          <em><?= e((string) parse_url($link['url'], PHP_URL_HOST) ?: $link['url']) ?></em>
        </span>
        <?php if ($link['nofollow']): ?><span class="tag">nofollow</span><?php endif; ?>
      </a>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<div class="card friend-apply" style="margin-top: var(--sp-6);">
  <div class="card-head">
    <h2 class="card-title"><?= icon('link') ?>申请友链</h2>
  </div>
  <div class="card-body">
    <?php foreach (preg_split('/\r\n|\r|\n/', $applyNote) ?: [] as $line): ?>
      <?php if (trim($line) !== ''): ?>
        <p class="friend-apply-line"><?= e($line) ?></p>
      <?php endif; ?>
    <?php endforeach; ?>
  </div>
</div>
