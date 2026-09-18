<?php use App\Core\View;
use App\Services\SiteOps;

/**
 * 页脚（docs/站点运营模块设计.md §九）
 *
 * 友链投放面：前台页面各自决定 home / sub；
 * 关闭即零痕迹：无友链 / 无备案 / 赞助关闭时对应节点不输出。
 *
 * @var string $friendPlacement 投放面（home / sub），默认 sub
 */
$footer = SiteOps::footer();
$links = SiteOps::friendLinks($friendPlacement ?? 'sub');
$adSlot = SiteOps::adSlot('footer');
$sponsor = SiteOps::sponsor();
?><footer class="site-footer">
  <?php View::include('partials/ad-slot', ['slot' => $adSlot]); ?>

  <div class="container site-footer-inner">
    <?php if ($footer['brand_desc'] !== ''): ?>
      <p class="site-footer-brand"><?= e($footer['brand_desc']) ?></p>
    <?php endif; ?>

    <?php if ($links !== []): ?>
      <nav class="site-footer-links" aria-label="友情链接">
        <span class="site-footer-links-label">友情链接：</span>
        <?php foreach ($links as $link): ?>
          <a href="<?= e($link['url']) ?>"
             <?= str_starts_with($link['url'], '/') ? '' : 'target="_blank" rel="noopener' . ($link['nofollow'] ? ' nofollow' : '') . '"' ?>>
            <?= e($link['name']) ?>
          </a>
        <?php endforeach; ?>
      </nav>
    <?php endif; ?>

    <?php if ($footer['icp_number'] !== '' || $footer['police_number'] !== '' || $footer['statement'] !== ''): ?>
      <div class="site-footer-legal">
        <?php if ($footer['icp_number'] !== ''): ?>
          <?php if ($footer['icp_url'] !== null): ?>
            <a href="<?= e($footer['icp_url']) ?>" target="_blank" rel="noopener nofollow"><?= e($footer['icp_number']) ?></a>
          <?php else: ?>
            <span><?= e($footer['icp_number']) ?></span>
          <?php endif; ?>
        <?php endif; ?>
        <?php if ($footer['police_number'] !== ''): ?>
          <?php if ($footer['police_url'] !== null): ?>
            <a href="<?= e($footer['police_url']) ?>" target="_blank" rel="noopener nofollow"><?= e($footer['police_number']) ?></a>
          <?php else: ?>
            <span><?= e($footer['police_number']) ?></span>
          <?php endif; ?>
        <?php endif; ?>
        <?php if ($footer['statement'] !== ''): ?>
          <p class="site-footer-statement"><?= e($footer['statement']) ?></p>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <nav class="site-footer-links" aria-label="页脚导航">
      <a href="<?= e(url('/tools')) ?>">全部工具</a>
      <?php if ($sponsor !== null && $sponsor['show_footer']): ?>
        <a href="<?= e(url('/sponsor')) ?>"><?= icon('heart') ?>支持本站</a>
      <?php endif; ?>
      <a href="<?= e(url('/admin')) ?>">后台</a>
    </nav>

    <div><?= e($footer['copyright']) ?></div>
  </div>
</footer>
