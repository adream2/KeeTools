<?php
/**
 * 错误页
 *
 * @var int $status
 * @var string $message
 * @var Throwable $exception
 * @var bool $showDetail 是否显示技术细节（仅调试模式）
 */

use App\Core\Security;

$titles = [
    403 => '没有权限访问',
    404 => '页面不存在',
    405 => '请求方法不被允许',
    419 => '页面已过期',
    500 => '服务出错了',
];
$title = $titles[$status] ?? '出错了';
?><!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($status . ' ' . $title) ?></title>
<link rel="stylesheet" href="<?= e(asset('css/site.bundle.css')) ?>">
</head>
<body>
<div class="site">
  <main class="site-main" id="main">
    <div class="container">
      <div class="card" style="max-width: 40rem; margin: var(--sp-12) auto;">
        <div class="card-body">
          <h1 style="font-size: var(--fs-2xl); margin-bottom: var(--sp-3);">
            <?= e((string) $status) ?> · <?= e($title) ?>
          </h1>

          <?php if ($showDetail): ?>
            <div class="alert alert-danger">
              <div>
                <strong><?= e($exception::class) ?></strong><br>
                <?= e($message) ?><br>
                <span style="color: var(--c-text-muted); font-size: var(--fs-xs);">
                  <?= e($exception->getFile()) ?>:<?= e((string) $exception->getLine()) ?>
                </span>
              </div>
            </div>
            <details style="margin-top: var(--sp-4);">
              <summary style="cursor: pointer; color: var(--c-text-muted); font-size: var(--fs-sm);">
                查看堆栈
              </summary>
              <pre style="margin-top: var(--sp-3); padding: var(--sp-4); background: var(--c-bg-mute); border-radius: var(--r-md); overflow-x: auto; font-size: var(--fs-xs);"><?= e($exception->getTraceAsString()) ?></pre>
            </details>
          <?php else: ?>
            <p style="color: var(--c-text-muted); line-height: var(--lh-loose);">
              服务器遇到问题，请稍后再试。若持续出现，请联系管理员。
            </p>
          <?php endif; ?>

          <div style="margin-top: var(--sp-6);">
            <a class="btn btn-primary" href="<?= e(url('/')) ?>">返回首页</a>
          </div>
        </div>
      </div>
    </div>
  </main>
</div>
</body>
</html>
