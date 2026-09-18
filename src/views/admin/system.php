<?php
/**
 * 系统信息
 *
 * @var string $phpVersion
 * @var string $sqliteVersion
 * @var string $appEnv
 * @var string $appDebug
 * @var string $appKeyState
 * @var bool   $appKeyValid
 * @var string $adminPassword
 * @var string $passwordless
 * @var list<string> $passwordlessIps
 * @var string $timezone
 * @var array<string, bool> $extensions
 * @var array<string, string> $paths
 * @var array<string, bool> $writable
 * @var array<string, int> $dbSizes
 * @var int    $overrideCount
 */
use App\Core\Csrf;
?><div class="page-head">
  <h1 class="page-title">系统信息</h1>
  <p class="page-desc">环境配置回显（敏感项只显示是否已设置）与运行状态检测</p>
</div>

<?php if ($overrideCount > 0): ?>
<div class="alert alert-warning">
  <?= icon('alert-triangle') ?>
  <div>
    有 <strong><?= e((string) $overrideCount) ?></strong> 个工具的修改暂存在数据库覆盖层（降级模式）。
    若 tools/ 目录已恢复可写，可一键落盘回 manifest 文件。
  </div>
</div>
<div style="margin-bottom: var(--sp-5);">
  <form method="post" action="<?= e(url('/admin/system/flush-overrides')) ?>">
    <?= Csrf::field() ?>
    <button class="btn btn-primary" type="submit"><?= icon('download') ?>落盘降级覆盖</button>
  </form>
</div>
<?php endif; ?>

<div class="dash-grid">
  <div class="card">
    <div class="card-head"><h2 class="card-title">运行环境</h2></div>
    <div class="card-body">
      <div class="tool-meta-list">
        <div class="tool-meta-row">
          <span class="tool-meta-label">PHP 版本</span>
          <span class="tool-meta-value tool-meta-mono"><?= e($phpVersion) ?></span>
        </div>
        <div class="tool-meta-row">
          <span class="tool-meta-label">SQLite 版本</span>
          <span class="tool-meta-value tool-meta-mono"><?= e($sqliteVersion) ?></span>
        </div>
        <div class="tool-meta-row">
          <span class="tool-meta-label">APP_ENV</span>
          <span class="tool-meta-value"><?= e($appEnv) ?></span>
        </div>
        <div class="tool-meta-row">
          <span class="tool-meta-label">APP_DEBUG</span>
          <span class="tool-meta-value"><?= e($appDebug) ?></span>
        </div>
        <div class="tool-meta-row">
          <span class="tool-meta-label">时区</span>
          <span class="tool-meta-value tool-meta-mono"><?= e($timezone) ?></span>
        </div>
        <?php foreach ($extensions as $name => $loaded): ?>
          <div class="tool-meta-row">
            <span class="tool-meta-label">扩展 <?= e($name) ?></span>
            <span class="tool-meta-value"><?= $loaded ? '✓ 已加载' : '✗ 未加载' ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-head"><h2 class="card-title">安全状态</h2></div>
    <div class="card-body">
      <div class="tool-meta-list">
        <div class="tool-meta-row">
          <span class="tool-meta-label">APP_KEY（签名密钥）</span>
          <span class="tool-meta-value">
            <?= e($appKeyState) ?>
            <?= $appKeyState === '已设置' && !$appKeyValid ? '<span class="tag tag-danger">格式非 64 位 hex</span>' : '' ?>
            <?= $appKeyState === '已设置' && $appKeyValid ? '<span class="tag tag-success">格式合法</span>' : '' ?>
          </span>
        </div>
        <div class="tool-meta-row">
          <span class="tool-meta-label">ADMIN_PASSWORD</span>
          <span class="tool-meta-value"><?= e($adminPassword) ?></span>
        </div>
        <div class="tool-meta-row">
          <span class="tool-meta-label">无密码后台</span>
          <span class="tool-meta-value"><?= e($passwordless) ?></span>
        </div>
        <?php if ($passwordlessIps !== []): ?>
          <div class="tool-meta-row">
            <span class="tool-meta-label">白名单 IP</span>
            <span class="tool-meta-value tool-meta-mono"><?= e(implode(', ', $passwordlessIps)) ?></span>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<div class="dash-grid" style="margin-top: var(--sp-5);">
  <div class="card">
    <div class="card-head"><h2 class="card-title">写入权限</h2></div>
    <div class="card-body">
      <div class="tool-meta-list">
        <?php foreach ($writable as $label => $ok): ?>
          <div class="tool-meta-row">
            <span class="tool-meta-label"><?= e($label) ?> 可写</span>
            <span class="tool-meta-value"><?= $ok ? '✓ 正常' : '✗ 不可写' ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-head"><h2 class="card-title">数据与路径</h2></div>
    <div class="card-body">
      <div class="tool-meta-list">
        <div class="tool-meta-row">
          <span class="tool-meta-label">storage/app.db</span>
          <span class="tool-meta-value tool-meta-mono"><?= e(number_format($dbSizes['app.db'] / 1024, 1)) ?> KB</span>
        </div>
        <div class="tool-meta-row">
          <span class="tool-meta-label">storage/stats.db</span>
          <span class="tool-meta-value tool-meta-mono"><?= e(number_format($dbSizes['stats.db'] / 1024, 1)) ?> KB</span>
        </div>
        <?php foreach ($paths as $label => $path): ?>
          <div class="tool-meta-row">
            <span class="tool-meta-label"><?= e($label) ?></span>
            <span class="tool-meta-value table-sub"><?= e($path) ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>
