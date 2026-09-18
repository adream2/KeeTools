<?php
/**
 * 页脚
 */
?><footer class="site-footer">
  <div class="container site-footer-inner">
    <div>
      &copy; <?= e(date('Y')) ?> <?= e(site_name()) ?> · 免费课堂工具集
    </div>
    <nav class="site-footer-links" aria-label="页脚导航">
      <a href="<?= e(url('/tools')) ?>">全部工具</a>
      <a href="<?= e(url('/admin')) ?>">后台</a>
    </nav>
  </div>
</footer>
