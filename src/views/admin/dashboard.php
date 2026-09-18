<?php
/**
 * 仪表盘
 *
 * @var array{tools: int, published: int, featured: int, mismatch: int, categories: int, netdisks: int} $stats
 * @var bool $toolsWritable
 * @var bool $storageWritable
 * @var bool $envFileExists
 */
use App\Core\Csrf;
?><div class="page-head">
  <h1 class="page-title">仪表盘</h1>
  <p class="page-desc">站点运行概况与快捷操作</p>
</div>

<?php if (!$envFileExists): ?>
  <div class="alert alert-warning">
    <?= icon('alert-triangle') ?>
    <div>未找到 <code>.env</code> 文件：请复制 <code>.env.example</code> 为 <code>.env</code> 并配置 APP_KEY、管理员凭据。</div>
  </div>
<?php endif; ?>

<div class="stat-grid">
  <a class="stat-card" href="<?= e(url('/admin/tools')) ?>">
    <span class="stat-num"><?= e((string) $stats['tools']) ?></span>
    <span class="stat-label">工具总数</span>
  </a>
  <a class="stat-card" href="<?= e(url('/admin/tools')) ?>">
    <span class="stat-num"><?= e((string) $stats['published']) ?></span>
    <span class="stat-label">已上架</span>
  </a>
  <a class="stat-card" href="<?= e(url('/admin/tools')) ?>">
    <span class="stat-num"><?= e((string) $stats['featured']) ?></span>
    <span class="stat-label">推荐位</span>
  </a>
  <a class="stat-card<?= $stats['mismatch'] > 0 ? ' stat-card-danger' : '' ?>" href="<?= e(url('/admin/tools')) ?>">
    <span class="stat-num"><?= e((string) $stats['mismatch']) ?></span>
    <span class="stat-label">版本不一致</span>
  </a>
  <a class="stat-card" href="<?= e(url('/admin/categories')) ?>">
    <span class="stat-num"><?= e((string) $stats['categories']) ?></span>
    <span class="stat-label">分类</span>
  </a>
  <a class="stat-card" href="<?= e(url('/admin/tools')) ?>">
    <span class="stat-num"><?= e((string) $stats['netdisks']) ?></span>
    <span class="stat-label">网盘链接（P1）</span>
  </a>
</div>

<div class="dash-grid">
  <div class="card">
    <div class="card-head">
      <h2 class="card-title">快捷操作</h2>
    </div>
    <div class="card-body dash-actions">
      <form method="post" action="<?= e(url('/admin/tools/scan')) ?>">
        <?= Csrf::field() ?>
        <button class="btn btn-primary" type="submit"><?= icon('refresh-cw') ?>扫描同步工具</button>
      </form>
      <a class="btn" href="<?= e(url('/admin/tools')) ?>"><?= icon('wrench') ?>管理工具</a>
      <a class="btn" href="<?= e(url('/admin/categories')) ?>"><?= icon('folder-tree') ?>管理分类</a>
      <a class="btn" href="<?= e(url('/admin/settings')) ?>"><?= icon('settings') ?>站点设置</a>
    </div>
  </div>

  <div class="card">
    <div class="card-head">
      <h2 class="card-title">环境健康</h2>
    </div>
    <div class="card-body">
      <div class="tool-meta-list">
        <div class="tool-meta-row">
          <span class="tool-meta-label">tools/ 可写（manifest 回写）</span>
          <span class="tool-meta-value"><?= $toolsWritable ? '✓ 正常' : '✗ 不可写（降级模式）' ?></span>
        </div>
        <div class="tool-meta-row">
          <span class="tool-meta-label">storage/ 可写（数据库）</span>
          <span class="tool-meta-value"><?= $storageWritable ? '✓ 正常' : '✗ 不可写' ?></span>
        </div>
      </div>
    </div>
  </div>
</div>
