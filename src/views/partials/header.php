<?php
/**
 * 页头
 *
 * 主导航可后台配置（site_config.header_nav，未配置回退默认两项）。
 */
use App\Services\SiteOps;

$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
?><header class="site-header">
  <div class="container site-header-inner">
    <a class="site-logo" href="<?= e(url('/')) ?>">
      <span class="site-logo-mark"><?= icon('grid-2x2') ?></span>
      <span><?= e(site_name()) ?></span>
    </a>

    <nav class="site-nav" aria-label="主导航">
      <?php foreach (SiteOps::headerNav() as $i => $item): ?>
        <?php $isActive = $currentPath === $item['url'] || ($item['url'] !== '/' && str_starts_with($currentPath, $item['url'])); ?>
        <a class="site-nav-link<?= $i === 0 && $currentPath === '/' ? ' is-active' : ($isActive ? ' is-active' : '') ?>"
           href="<?= e($item['url']) ?>"
           <?= str_starts_with($item['url'], '/') ? '' : 'target="_blank" rel="noopener"' ?>><?= e($item['label']) ?></a>
      <?php endforeach; ?>
    </nav>

    <div class="site-header-actions">
      <form class="form-search site-header-search" action="<?= e(url('/search')) ?>" method="get" role="search">
        <label class="visually-hidden" for="header-q">搜索工具</label>
        <input class="form-input" type="search" id="header-q" name="q" placeholder="搜索工具…"
               autocomplete="off" maxlength="50">
        <button class="btn btn-primary" type="submit"><?= icon('search') ?>搜索</button>
      </form>
      <a class="btn btn-ghost btn-sm" href="<?= e(url('/admin')) ?>"><?= icon('settings') ?>后台</a>
    </div>
  </div>
</header>
