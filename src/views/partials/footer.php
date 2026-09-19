<?php use App\Core\View;
use App\Services\SiteOps;

/**
 * 页脚（docs/站点运营模块设计.md §四/§九）
 *
 * 友链投放（用户定稿）：两种情况——
 *   全站页脚常显（placement home/all，SiteOps::friendLinks()）
 *   仅友链独立页（/friend-links，SiteOps::allFriendLinks()，与申请友链说明同页）
 * 页脚通过「友情链接」入口指向独立页；关闭即零痕迹。
 */
$footer = SiteOps::footer();
$links = SiteOps::friendLinks();
$adSlot = SiteOps::adSlot('footer');
$sponsor = SiteOps::sponsor();

// 快捷导航：后台可配置（footer_nav），未配置回退内置导航（含 QQ 社群，可在后台自定义时增删）
$navLinks = SiteOps::footerNav();
if ($navLinks === []) {
    $navLinks = [['label' => '全部工具', 'url' => url('/tools')]];
    $navLinks[] = ['label' => '关于本站', 'url' => url('/about')];
    // 「支持本站」不放快速导航：品牌列左侧已有同功能按钮（用户定稿 2026-09-19）
    $navLinks[] = ['label' => 'QQ 群', 'url' => 'https://qm.qq.com/q/djTRxXXQNq']; // et-allow-external 社群加入链接，用户主动跳转
    $navLinks[] = ['label' => 'QQ 频道', 'url' => 'https://pd.qq.com/s/fhc0uxdjn']; // et-allow-external 社群加入链接，用户主动跳转
    // 后台入口不暴露在前台页面上（用户定稿 2026-09-19），管理员直接访问 /admin
}
?><footer class="site-footer">
  <div class="container">
    <div class="site-footer-top">
      <div class="site-footer-brand">
        <a class="site-footer-logo" href="<?= e(url('/')) ?>">
          <img class="site-footer-logo-mark" src="<?= e(url('/favicon.svg')) ?>" alt="" width="32" height="32">
          <span><?= e(site_name()) ?></span>
        </a>
        <?php if ($footer['brand_desc'] !== ''): ?>
          <p class="site-footer-desc"><?= e($footer['brand_desc']) ?></p>
        <?php endif; ?>
        <?php if ($footer['contact_email'] !== '' || $footer['contact_text'] !== []): ?>
          <ul class="site-footer-contact">
            <?php if ($footer['contact_email'] !== ''): ?>
              <li>
                <?php if (filter_var($footer['contact_email'], FILTER_VALIDATE_EMAIL)): ?>
                  <a href="mailto:<?= e($footer['contact_email']) ?>">邮箱：<?= e($footer['contact_email']) ?></a>
                <?php else: ?>
                  <span>邮箱：<?= e($footer['contact_email']) ?></span>
                <?php endif; ?>
              </li>
            <?php endif; ?>
            <?php foreach ($footer['contact_text'] as $line): ?>
              <li><span><?= e($line) ?></span></li>
            <?php endforeach; ?>
          </ul>
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

      <?php if ($links !== [] || SiteOps::hasFriendPageContent()): ?>
        <nav class="site-footer-col site-footer-col--wide" aria-label="友情链接">
          <h2 class="site-footer-col-title">
            友情链接
            <a class="site-footer-apply" href="<?= e(url('/friend-links')) ?>">更多 →</a>
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
          <?php else: ?>
            <p class="site-footer-desc">
              暂无友链，<a href="<?= e(url('/friend-links')) ?>">申请友链</a>与我们一起成长。
            </p>
          <?php endif; ?>
        </nav>
      <?php endif; ?>
    </div>

    <?php View::include('partials/ad-slot', ['slot' => $adSlot, 'position' => 'footer']); ?>

    <div class="site-footer-bottom">
      <?php if ($footer['icp_number'] !== '' || $footer['police_number'] !== '' || $footer['statement'] !== '' || $footer['show_sitemap']): ?>
        <p class="site-footer-legal">
          <?php if ($footer['show_sitemap']): ?>
            <a href="<?= e(url('/sitemap.xml')) ?>">站点地图</a>
          <?php endif; ?>
          <?php if ($footer['icp_number'] !== ''): ?>
            <?php if ($footer['icp_url'] !== null): ?>
              <a href="<?= e($footer['icp_url']) ?>" target="_blank" rel="noopener nofollow"><?= e($footer['icp_number']) ?></a>
            <?php else: ?>
              <span><?= e($footer['icp_number']) ?></span>
            <?php endif; ?>
          <?php endif; ?>
          <?php if ($footer['police_number'] !== ''): ?>
            <?php if ($footer['police_url'] !== null): ?>
              <a class="site-footer-police" href="<?= e($footer['police_url']) ?>" target="_blank" rel="noopener nofollow">
                <img class="site-footer-beian" src="<?= e(asset('img/beian.png')) ?>" alt="公安联网备案徽标" width="20" height="20">
                <?= e($footer['police_number']) ?>
              </a>
            <?php else: ?>
              <span class="site-footer-police">
                <img class="site-footer-beian" src="<?= e(asset('img/beian.png')) ?>" alt="公安联网备案徽标" width="20" height="20">
                <?= e($footer['police_number']) ?>
              </span>
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
