<?php
/**
 * 前台布局
 *
 * 模板变量（由 View::render 注入）：
 *   $content        —— 子模板渲染结果（必填）
 *   $pageTitle      —— 页面标题
 *   $pageDesc       —— meta description
 *   $pageRobots     —— robots 指令（默认 index,follow；搜索页/归档页传 noindex）
 *   $pageKeywords   —— meta keywords（可选）
 *   $pageCanonical  —— 规范链接绝对地址（默认当前路径）
 *
 * @var string $content
 * @var string|null $pageTitle
 * @var string|null $pageDesc
 * @var string|null $pageRobots
 * @var string|null $pageKeywords
 * @var string|null $pageCanonical
 */

use App\Core\View;
use App\Services\SiteOps;

$title = isset($pageTitle) && $pageTitle !== '' ? $pageTitle : site_name();
$desc = $pageDesc ?? site_description();
$announcements = SiteOps::announcements();

// SEO：canonical 与 robots（P4 §三）
$requestPath = (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?? '/');
$origin = site_url();
$canonical = $pageCanonical ?? ($origin . $requestPath);
$robots = $pageRobots ?? 'index,follow';
$keywords = trim((string) ($pageKeywords ?? ''));

// 后台「代码注入」（原始 HTML，仅 admin 可写）：开关关闭或代码为空 = 零痕迹
$injectHead = \App\Core\Config::bool('inject_head_enabled') ? \App\Core\Config::string('inject_head_code') : '';
$injectFoot = \App\Core\Config::bool('inject_foot_enabled') ? \App\Core\Config::string('inject_foot_code') : '';
?><!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?></title>
<meta name="description" content="<?= e($desc) ?>">
<?php if ($keywords !== ''): ?>
<meta name="keywords" content="<?= e($keywords) ?>">
<?php endif; ?>
<meta name="robots" content="<?= e($robots) ?>">
<link rel="canonical" href="<?= e($canonical) ?>">
<link rel="icon" type="image/svg+xml" href="<?= e(url('/favicon.svg')) ?>">
<meta property="og:type" content="website">
<meta property="og:site_name" content="<?= e(site_name()) ?>">
<meta property="og:title" content="<?= e($title) ?>">
<meta property="og:description" content="<?= e($desc) ?>">
<meta property="og:url" content="<?= e($canonical) ?>">
<meta name="twitter:card" content="summary">
<link rel="stylesheet" href="<?= e(asset('css/site.bundle.css')) ?>">
<?php if ($injectHead !== ''): ?>
<?= $injectHead /* 原始 HTML：后台「代码注入」，仅 admin 可写 */ ?>
<?php endif; ?>
</head>
<body>
<div class="site">
  <a class="skip-link" href="#main">跳到主要内容</a>

  <?php View::include('partials/header'); ?>

  <?php View::include('partials/announcements', [
      'announcements' => $announcements,
      'barCount'      => SiteOps::announceBarCount(),
      'closable'      => \App\Core\Config::bool('announce_closable', true),
      'centered'      => \App\Core\Config::bool('announce_center', false),
  ]); ?>

  <main class="site-main" id="main">
    <div class="container">
      <?= $content ?>
    </div>
  </main>

  <?php View::include('partials/footer'); ?>
</div>

<script src="<?= e(asset('js/site.js')) ?>" defer></script>
<?php if ($injectFoot !== ''): ?>
<?= $injectFoot /* 原始 HTML：后台「代码注入」，仅 admin 可写 */ ?>
<?php endif; ?>
</body>
</html>
