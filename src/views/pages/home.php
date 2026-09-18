<?php
/**
 * 首页
 *
 * @var bool   $dbReady 数据库是否已初始化
 * @var list<array<string, mixed>> $featured 推荐工具（不足时为最新工具）
 * @var list<array{name: string, slug: string, icon: ?string, count: int, subjects: list<array<string, mixed>>}> $stages
 */

use App\Core\Config;
use App\Core\View;
use App\Services\SiteOps;
?><?php View::include('partials/ad-slot', ['slot' => SiteOps::adSlot('home_top')]); ?>

<section class="hero">
  <h1 class="hero-title"><?= e(site_name()) ?><span class="hero-title-cn">课工具</span></h1>
  <p class="hero-subtitle">
    面向中小学老师的免费课堂工具集。<br>
    单文件即开即用，无需安装，断网也能用。
  </p>

  <form class="form-search hero-search" action="<?= e(url('/search')) ?>" method="get" role="search">
    <label class="visually-hidden" for="hero-q">搜索工具</label>
    <input class="form-input" type="search" id="hero-q" name="q"
           placeholder="搜索工具名称、学科或用途…" autocomplete="off" maxlength="50">
    <button class="btn btn-primary btn-lg" type="submit">搜索</button>
  </form>

  <div class="hero-features">
    <span class="hero-feature"><?= icon('check') ?>零依赖单文件</span>
    <span class="hero-feature"><?= icon('check') ?>数据存本地</span>
    <span class="hero-feature"><?= icon('check') ?>完全免费</span>
  </div>
</section>

<?php if (!$dbReady): ?>
  <div class="alert alert-warning" style="margin-top: var(--sp-6);">
    <div>
      <strong>数据库尚未初始化。</strong>
      请复制 <code>.env.example</code> 为 <code>.env</code>，然后执行
      <code>php scripts/init_db.php</code> 建表。
      当前页面仅验证框架连通性，工具数据尚未接入。
    </div>
  </div>
<?php endif; ?>

<?php if ($stages !== []): ?>
<section class="section">
  <div class="section-head">
    <h2 class="section-title">按学段浏览</h2>
  </div>
  <div class="stage-grid">
    <?php foreach ($stages as $stage): ?>
      <div class="stage-card">
        <a class="stage-card-head" href="<?= e(url('/category/' . rawurlencode($stage['slug']))) ?>">
          <span class="stage-icon"><?= icon($stage['icon'] ?? 'book-open') ?></span>
          <span class="stage-card-head-text">
            <span class="stage-name"><?= e($stage['name']) ?></span>
            <span class="stage-count"><?= e((string) $stage['count']) ?> 个工具</span>
          </span>
          <?= icon('chevron-right', 'stage-arrow') ?>
        </a>
        <div class="stage-chips">
          <?php
          // 优先展示已有工具的学科，最多 8 个；全空时展示前 4 个学科做预览
          $withTools = array_values(array_filter(
              $stage['subjects'],
              static fn (array $s): bool => $s['count'] > 0
          ));
          $preview = $withTools !== [] ? array_slice($withTools, 0, 8) : array_slice($stage['subjects'], 0, 4);
          ?>
          <?php foreach ($preview as $subject): ?>
            <a class="stage-chip" href="<?= e(url('/category/' . rawurlencode($subject['slug']))) ?>">
              <?= e($subject['name']) ?><?php if ($subject['count'] > 0): ?><span class="stage-chip-count"><?= e((string) $subject['count']) ?></span><?php endif; ?>
            </a>
          <?php endforeach; ?>
          <?php if (count($stage['subjects']) > count($preview)): ?>
            <a class="stage-chip stage-chip-more" href="<?= e(url('/category/' . rawurlencode($stage['slug']))) ?>">
              全部 <?= e((string) count($stage['subjects'])) ?> 科
            </a>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<section class="section">
  <div class="section-head">
    <h2 class="section-title">精选工具</h2>
    <a class="section-more" href="<?= e(url('/tools')) ?>">查看全部 →</a>
  </div>
  <?php if ($featured !== []): ?>
    <div class="tool-grid">
      <?php foreach ($featured as $tool): ?>
        <?php View::include('partials/tool-card', ['tool' => $tool]); ?>
      <?php endforeach; ?>
    </div>
  <?php elseif ($dbReady): ?>
    <div class="empty">
      <div class="empty-title">暂无工具</div>
      <div>在后台执行「扫描同步」后，工具会出现在这里。</div>
    </div>
  <?php endif; ?>
</section>

<?php if (SiteOps::sponsorEnabled() && Config::bool('sponsor_show_home_cta', true)): ?>
<section class="section">
  <div class="card sponsor-home-cta">
    <div class="card-body">
      <h2 class="section-title"><?= icon('heart') ?>支持课工具</h2>
      <p class="tool-get-note">所有工具免费，站点靠各位老师的自愿支持维持。哪怕一杯奶茶，都是持续更新的动力。</p>
      <a class="btn btn-primary" href="<?= e(url('/sponsor')) ?>">支持本站</a>
    </div>
  </div>
</section>
<?php endif; ?>
