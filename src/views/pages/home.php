<?php
/**
 * 首页
 *
 * @var bool $dbReady 数据库是否已初始化
 */
?><section class="hero">
  <h1 class="hero-title"><?= e(site_name()) ?></h1>
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
    <span class="hero-feature">✓ 零依赖单文件</span>
    <span class="hero-feature">✓ 数据存本地</span>
    <span class="hero-feature">✓ 完全免费</span>
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

<section class="section">
  <div class="section-head">
    <h2 class="section-title">按学科浏览</h2>
  </div>
  <div class="cat-grid">
    <?php
    // P0 阶段为静态占位，用于验证布局；P1 阶段改为读取 categories 表
    $placeholderCategories = [
        '语文' => 0, '数学' => 0, '英语' => 0, '物理' => 0,
        '化学' => 0, '生物' => 0, '历史' => 0, '地理' => 0,
    ];
    ?>
    <?php foreach ($placeholderCategories as $name => $count): ?>
      <a class="cat-item" href="<?= e(url('/category/' . rawurlencode((string) $name))) ?>">
        <span class="cat-item-name"><?= e($name) ?></span>
        <span class="cat-item-count"><?= e((string) $count) ?> 个工具</span>
      </a>
    <?php endforeach; ?>
  </div>
</section>

<section class="section">
  <div class="section-head">
    <h2 class="section-title">最新工具</h2>
    <a class="section-more" href="<?= e(url('/tools')) ?>">查看全部 →</a>
  </div>
  <div class="empty">
    <div class="empty-title">暂无工具</div>
    <div>工具接入数据库后在此展示。P0 阶段仅验证框架链路。</div>
  </div>
</section>
