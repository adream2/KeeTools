<?php use App\Core\View;
use App\Services\SiteOps;

/**
 * 页脚（docs/站点运营模块设计.md §四/§九）
 *
 * 布局：上区三栏（品牌 / 快速导航 / 友链）→ 广告位 → 备案行 → 版权行。
 * 友链投放面由页面通过 $friendPlacement 决定（首页 home / 内页 sub）；
 * 关闭即零痕迹：无友链 / 无备案 / 赞助关闭时对应节点不输出。
 *
 * @var string $friendPlacement 投放面（home / sub），默认 sub
 */
$footer = SiteOps::footer();
$links = SiteOps::friendLinks($friendPlacement ?? 'sub');
$adSlot = SiteOps::adSlot('footer');
$sponsor = SiteOps::sponsor();
$applyUrl = \App\Core\Security::safeExternalUrl(trim(\App\Core\Config::string('friend_link_apply_url')));

// 快捷导航：后台可配置（footer_nav），未配置回退内置导航
$navLinks = SiteOps::footerNav();
if ($navLinks === []) {
    $navLinks = [['label' => '全部工具', 'url' => url('/tools')]];
    if ($sponsor !== null && $sponsor['show_footer']) {
        $navLinks[] = ['label' => '支持本站', 'url' => url('/sponsor')];
    }
    $navLinks[] = ['label' => '后台', 'url' => url('/admin')];
}
?><footer class="site-footer">
  <div class="container">
    <div class="site-footer-top">
      <div class="site-footer-brand">
        <a class="site-footer-logo" href="<?= e(url('/')) ?>">
          <span class="site-footer-logo-mark"><?= icon('grid-2x2') ?></span>
          <span><?= e(site_name()) ?></span>
        </a>
        <?php if ($footer['brand_desc'] !== ''): ?>
          <p class="site-footer-desc"><?= e($footer['brand_desc']) ?></p>
        <?php endif; ?>
        <?php if ($sponsor !== null && $sponsor['show_footer']): ?>
          <a class="btn btn-sm site-footer-sponsor" href="<?= e(url('/sponsor')) ?>">
            <?= icon('heart') ?>支持本站
          </a>
        <?php endif; ?>
      </div>

      <nav class="site-footer-col" aria-label="页脚导航">
        <h2 class="site-footer-col-title">快速导航</h2>
        <ul class="site-footer-col-list">
          <?php foreach ($navLinks as $item): ?>
            <li>
              <a href="<?= e($item['url']) ?>"
                 <?= str_starts_with($item['url'], '/') ? '' : 'target="_blank" rel="noopener"' ?>><?= e($item['label']) ?></a>
            </li>
          <?php endforeach; ?>
        </ul>
      </nav>

      <?php if ($links !== [] || $applyUrl !== null): ?>
        <nav class="site-footer-col site-footer-col--wide" aria-label="友情链接">
          <h2 class="site-footer-col-title">
            友情链接
            <?php if ($applyUrl !== null): ?>
              <a class="site-footer-apply" href="<?= e($applyUrl) ?>"
                 <?= str_starts_with($applyUrl, '/') ? '' : 'target="_blank" rel="noopener"' ?>>申请友链</a>
            <?php endif; ?>
          </h2>
          <?php if ($links !== []): ?>
          <ul class="site-footer-col-list site-footer-friends">
            <?php foreach ($links as $link): ?>
              <li>
                <a href="<?= e($link['url']) ?>"
                   <?= str_starts_with($link['url'], '/') ? '' : 'target="_blank" rel="noopener' . ($link['nofollow'] ? ' nofollow' : '') . '"' ?>>
                  <?= e($link['name']) ?>
                </a>
              </li>
            <?php endforeach; ?>
          </ul>
          <?php endif; ?>
        </nav>
      <?php endif; ?>
    </div>

    <?php View::include('partials/ad-slot', ['slot' => $adSlot, 'position' => 'footer']); ?>

    <div class="site-footer-bottom">
      <?php if ($footer['icp_number'] !== '' || $footer['police_number'] !== '' || $footer['statement'] !== ''): ?>
        <p class="site-footer-legal">
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
            <span class="site-footer-statement"><?= e($footer['statement']) ?></span>
          <?php endif; ?>
        </p>
      <?php endif; ?>
      <p class="site-footer-copyright"><?= e($footer['copyright']) ?></p>
    </div>
  </div>
</footer>
