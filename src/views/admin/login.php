<?php
use App\Core\Csrf;

/**
 * 后台登录页（独立布局，无侧栏）
 *
 * @var string|null $error
 * @var string      $username
 * @var bool        $passwordless 无密码后台是否已配置
 * @var bool        $hasPassword  密码登录是否可用
 */
?><!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e('登录 — ' . site_name()) ?></title>
<link rel="stylesheet" href="<?= e(asset('css/site.bundle.css')) ?>">
</head>
<body class="admin-body-bg">
<div class="login-wrap">
  <div class="card login-card">
    <div class="card-body">
      <div class="login-head">
        <span class="login-logo"><?= icon('grid-2x2') ?></span>
        <h1 class="login-title"><?= e(site_name()) ?> 课工具后台</h1>
      </div>

      <?php if (!empty($error)): ?>
        <div class="alert alert-danger" style="margin-bottom: var(--sp-5);">
          <?= icon('alert-triangle') ?>
          <div><?= e($error) ?></div>
        </div>
      <?php endif; ?>

      <?php if ($hasPassword): ?>
        <form method="post" action="<?= e(url('/admin/login')) ?>">
          <?= Csrf::field() ?>
          <div class="form-group">
            <label class="form-label" for="username">用户名</label>
            <input class="form-input" type="text" id="username" name="username"
                   value="<?= e($username) ?>" autocomplete="username" required autofocus>
          </div>
          <div class="form-group">
            <label class="form-label" for="password">密码</label>
            <input class="form-input" type="password" id="password" name="password"
                   autocomplete="current-password" required>
          </div>
          <button class="btn btn-primary btn-block btn-lg" type="submit"><?= icon('log-out') ?>登录</button>
        </form>
      <?php elseif ($passwordless): ?>
        <div class="alert alert-info">
          <?= icon('info') ?>
          <div>
            未设置密码，当前运行在无密码模式。
            只有来源 IP 在 <code>ADMIN_PASSWORDLESS_IPS</code> 白名单内才可进入后台。
          </div>
        </div>
        <a class="btn btn-primary btn-block btn-lg" href="<?= e(url('/admin')) ?>">进入后台</a>
      <?php else: ?>
        <div class="alert alert-warning">
          <?= icon('alert-triangle') ?>
          <div>
            尚未配置管理员凭据：请在 <code>.env</code> 中设置
            <code>ADMIN_USERNAME</code> / <code>ADMIN_PASSWORD</code>，
            或启用 <code>ADMIN_PASSWORDLESS</code> + IP 白名单。
          </div>
        </div>
      <?php endif; ?>

      <div class="login-foot">
        <a href="<?= e(url('/')) ?>"><?= icon('arrow-left') ?>返回站点首页</a>
      </div>
    </div>
  </div>
</div>
</body>
</html>
