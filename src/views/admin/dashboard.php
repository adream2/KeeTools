<?php
/**
 * 仪表盘
 *
 * @var array{tools: int, published: int, featured: int, mismatch: int, categories: int, netdisks: int} $stats
 * @var list<array{month: string, added: int, total: int}> $growth 近 12 个月工具增长（P4 §四）
 * @var list<array{name: string, slug: string, count: int}> $distribution 学科分布
 * @var array{subjects: int, subjectsWithTools: int, reached: bool} $coverage 规模达标情况
 * @var bool $toolsWritable
 * @var bool $storageWritable
 * @var bool $envFileExists
 */
use App\Core\Csrf;

$growMax = 0;
foreach ($growth as $point) {
    $growMax = max($growMax, (int) $point['added']);
}
$distMax = 0;
foreach ($distribution as $item) {
    $distMax = max($distMax, (int) $item['count']);
}
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
        <div class="tool-meta-row">
          <span class="tool-meta-label">规模目标（≥30 工具 / ≥5 学科有工具）</span>
          <span class="tool-meta-value">
            <?= $coverage['reached'] ? '✓ 已达标' : '进行中' ?>
            （已覆盖 <?= e((string) $coverage['subjectsWithTools']) ?>/<?= e((string) $coverage['subjects']) ?> 学科）
          </span>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="dash-grid" style="margin-top: var(--sp-5);">
  <div class="card">
    <div class="card-head">
      <h2 class="card-title">工具数增长（近 12 个月）</h2>
    </div>
    <div class="card-body">
      <?php if ($growMax === 0): ?>
        <p class="tool-get-note">暂无入库记录 —— 先跑「扫描同步工具」。</p>
      <?php else: ?>
        <div class="grow-chart">
          <?php foreach ($growth as $point): ?>
            <?php
              $added = (int) $point['added'];
              $height = $added > 0 ? max(4, (int) round($added / $growMax * 100)) : 0;
            ?>
            <div class="grow-col" title="<?= e($point['month']) ?>：新增 <?= e((string) $added) ?>，累计 <?= e((string) $point['total']) ?>">
              <div class="grow-bar<?= $added === 0 ? ' grow-bar--empty' : '' ?>" style="height: <?= $height ?>%;"></div>
              <span class="grow-label"><?= e(substr((string) $point['month'], 5)) ?></span>
            </div>
          <?php endforeach; ?>
        </div>
        <p class="grow-hint">
          峰值月新增 <strong><?= e((string) $growMax) ?></strong> 个；
          当前累计 <strong><?= e((string) ($growth !== [] ? end($growth)['total'] : 0)) ?></strong> 个工具。
        </p>
      <?php endif; ?>
    </div>
  </div>

  <div class="card">
    <div class="card-head">
      <h2 class="card-title">学科分布</h2>
    </div>
    <div class="card-body">
      <?php if ($distribution === []): ?>
        <p class="tool-get-note">还没有工具挂到学科分类下（「通用」工具只挂学段）。</p>
      <?php else: ?>
        <div class="dist-list">
          <?php foreach ($distribution as $item): ?>
            <a class="dist-row" href="<?= e(url('/category/' . rawurlencode($item['slug']))) ?>">
              <span class="dist-name"><?= e($item['name']) ?></span>
              <span class="dist-track">
                <span class="dist-fill" style="width: <?= e((string) max(4, (int) round($item['count'] / $distMax * 100))) ?>%;"></span>
              </span>
              <span class="dist-count"><?= e((string) $item['count']) ?></span>
            </a>
          <?php endforeach; ?>
        </div>
        <p class="grow-hint">每学科 ≥ 3 个工具即可组成"可打包"的最小合集。</p>
      <?php endif; ?>
    </div>
  </div>
</div>
