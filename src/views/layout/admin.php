<?php
/**
 * 后台布局：左侧导航 + 右侧内容
 *
 * 模板变量：
 *   $content     子模板内容
 *   $pageTitle   页面标题
 *   $adminActive 侧栏激活项 key（dashboard / tools / categories / settings / system）
 */

use App\Core\Session;
use App\Core\View;

$title = isset($pageTitle) && $pageTitle !== '' ? $pageTitle : '后台 — ' . site_name();
$flash = Session::pullFlash();
$nav = [
    'dashboard'  => ['仪表盘', 'layout-dashboard'],
    'tools'      => ['工具管理', 'wrench'],
    'categories' => ['分类管理', 'folder-tree'],
    'netdisks'   => ['网盘管理', 'cloud'],
    'packages'   => ['离线包打包', 'package'],
    'stats'      => ['统计看板', 'bar-chart-3'],
    'settings'   => ['站点设置', 'settings'],
    'system'     => ['系统信息', 'info'],
];
?><!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?></title>
<link rel="stylesheet" href="<?= e(asset('css/site.bundle.css')) ?>">
</head>
<body class="admin-body-bg">
<div class="admin-shell">
  <aside class="admin-side">
    <a class="admin-logo" href="<?= e(url('/admin')) ?>">
      <span class="admin-logo-mark"><?= icon('grid-2x2') ?></span>
      <span><?= e(site_name()) ?></span>
    </a>

    <nav class="admin-nav" aria-label="后台导航">
      <?php foreach ($nav as $key => [$label, $navIcon]): ?>
        <a class="admin-nav-link<?= ($adminActive ?? '') === $key ? ' is-active' : '' ?>"
           href="<?= e(url('/admin' . ($key === 'dashboard' ? '' : '/' . $key))) ?>">
          <?= icon($navIcon) ?><?= e($label) ?>
        </a>
      <?php endforeach; ?>
    </nav>

    <div class="admin-side-foot">
      <a class="admin-nav-link" href="<?= e(url('/')) ?>" target="_blank" rel="noopener">
        <?= icon('external-link') ?>查看站点
      </a>
      <a class="admin-nav-link" href="<?= e(url('/admin/logout')) ?>">
        <?= icon('log-out') ?>退出登录
      </a>
    </div>
  </aside>

  <main class="admin-main">
    <div class="admin-content">
      <?php if (Session::get('admin_passwordless')): ?>
        <div class="alert alert-danger admin-passwordless-warning">
          <?= icon('alert-triangle') ?>
          <div><strong>当前为无密码模式</strong>：仅因来源 IP 在白名单内而免密登录，请勿在公共网络使用。</div>
        </div>
      <?php endif; ?>

      <?php foreach ($flash as $type => $message): ?>
        <div class="alert alert-<?= e($type) ?>">
          <?= $type === 'error' ? icon('alert-triangle') : icon('check') ?>
          <div><?= e($message) ?></div>
        </div>
      <?php endforeach; ?>

      <?= $content ?>
    </div>
  </main>
</div>
</body>
</html>
