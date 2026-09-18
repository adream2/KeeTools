<?php
/**
 * 前台布局
 *
 * 模板变量（由 View::render 注入）：
 *   $content   —— 子模板渲染结果（必填）
 *   $pageTitle —— 页面标题
 *   $pageDesc  —— meta description
 *
 * @var string $content
 * @var string|null $pageTitle
 * @var string|null $pageDesc
 */

use App\Core\View;

$title = isset($pageTitle) && $pageTitle !== '' ? $pageTitle : site_name();
$desc = $pageDesc ?? site_description();
?><!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?></title>
<meta name="description" content="<?= e($desc) ?>">
<link rel="stylesheet" href="<?= e(asset('css/site.bundle.css')) ?>">
</head>
<body>
<div class="site">
  <a class="skip-link" href="#main">跳到主要内容</a>

  <?php View::include('partials/header'); ?>

  <main class="site-main" id="main">
    <div class="container">
      <?= $content ?>
    </div>
  </main>

  <?php View::include('partials/footer'); ?>
</div>

<script src="<?= e(asset('js/site.js')) ?>" defer></script>
</body>
</html>
