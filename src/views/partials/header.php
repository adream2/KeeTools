<?php
/**
 * 页头
 *
 * 站点导航。分类动态渲染在 P1 接入数据库后补上，
 * 此处先放固定入口，保证布局结构定型。
 */
?><header class="site-header">
  <div class="container site-header-inner">
    <a class="site-logo" href="<?= e(url('/')) ?>">
      <span class="site-logo-mark" aria-hidden="true">◆</span>
      <span><?= e(site_name()) ?></span>
    </a>

    <nav class="site-nav" aria-label="主导航">
      <a class="site-nav-link is-active" href="<?= e(url('/')) ?>">首页</a>
      <a class="site-nav-link" href="<?= e(url('/tools')) ?>">全部工具</a>
    </nav>

    <div class="site-header-actions">
      <form class="form-search site-header-search" action="<?= e(url('/search')) ?>" method="get" role="search">
        <label class="visually-hidden" for="header-q">搜索工具</label>
        <input class="form-input" type="search" id="header-q" name="q" placeholder="搜索工具…"
               autocomplete="off" maxlength="50">
        <button class="btn btn-primary" type="submit">搜索</button>
      </form>
      <a class="btn btn-ghost btn-sm" href="<?= e(url('/admin')) ?>">后台</a>
    </div>
  </div>
</header>
