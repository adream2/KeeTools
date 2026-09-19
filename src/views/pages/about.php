<?php

/**
 * 关于页
 *
 * @var int $toolCount
 * @var int $subjectCount
 * @var array{categories: list<array{id: string, title: string, items: list<array{name: string, source: string, license: string, usage: string}>, notes: list<string>}>} $notices
 * @var bool $hasNotices
 * @var list<array{author: string, n: int}> $authors
 */
use App\Services\SiteOps;

// 页脚联系方式是否已在后台配置：决定「通过页脚联系」文案是否成立（未配置不承诺渠道）
$footerCfg = SiteOps::footer();
$hasFooterContact = $footerCfg['contact_email'] !== '' || $footerCfg['contact_text'] !== [];

$stats = [];
if ($toolCount > 0) {
    $stats[] = ['num' => (string) $toolCount, 'label' => '课堂工具'];
}
if ($subjectCount > 0) {
    $stats[] = ['num' => (string) $subjectCount, 'label' => '学科分类'];
}
$stats[] = ['num' => '100%', 'label' => '免费使用'];
$stats[] = ['num' => '0', 'label' => '外部链接'];
?><nav class="breadcrumb" aria-label="面包屑">
  <a href="<?= e(url('/')) ?>">首页</a>
  <span class="breadcrumb-sep">/</span>
  <span>关于本站</span>
</nav>

<div class="page-head">
  <h1 class="page-title">关于 <?= e(site_name()) ?></h1>
  <p class="page-desc">面向中小学课堂的免费工具集——老师、学生、家长都用得上</p>
</div>

<?php if ($stats !== []): ?>
  <div class="about-stats">
    <?php foreach ($stats as $s): ?>
      <div class="card about-stat">
        <strong><?= e($s['num']) ?></strong>
        <span><?= e($s['label']) ?></span>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<div class="card about-card">
  <div class="card-head">
    <h2 class="card-title"><?= icon('user') ?>来自站长的话</h2>
  </div>
  <div class="card-body">
    <p>这个项目由 <b>AI 辅助构建</b>，站长并非程序员出身。它的诞生很简单：一位教师朋友在课堂上
    经常用到这类小工具，而网上的工具不是夹着广告、要注册，就是断网就废——于是有了
    <?= e(site_name()) ?>。</p>
    <p>开发过程中必然存在许多不足与疏漏，<b>诚恳欢迎各位专业同学提出建设性建议与修复</b>——
    哪怕只是指出一个错别字，都是对这个项目实实在在的帮助。
    欢迎前往 <a href="https://github.com/adream2/KeeTools" target="_blank" rel="noopener nofollow">GitHub 仓库</a><!-- et-allow-external 项目仓库导航链接，非运行时资源 -->
    提 Issue 或参与共建。</p>
  </div>
</div>

<div class="card about-card">
  <div class="card-head">
    <h2 class="card-title"><?= icon('info') ?>这是什么</h2>
  </div>
  <div class="card-body">
    <p>每个工具就是一个独立的文件夹（尽可能打包为<b>单个 HTML 文件</b>）：双击打开就能用，
    断网也能用，投影到教室大屏不糊；个别需要大素材的大型工具会附带资源目录，随离线合集包整目录分发。</p>
    <p>本站只做工具，不做题库、不做课程、不做作业系统；全部工具免费使用。</p>
    <p>工具可在线使用，也可在工具详情页下载或按学段打包离线合集，方便没有网络的教室。</p>
  </div>
</div>

<div class="card about-card">
  <div class="card-head">
    <h2 class="card-title"><?= icon('book-open') ?>共建者</h2>
  </div>
  <div class="card-body">
    <p style="color: var(--c-text-muted);">每个工具都是社区成员的作品——名单由工具元数据自动汇总，
    你也可以成为其中一员（见<a href="https://github.com/adream2/KeeTools/blob/main/CONTRIBUTING.md"<!-- et-allow-external 项目仓库导航链接，非运行时资源 -->
    target="_blank" rel="noopener">参与指南</a>）。</p>
    <?php if ($authors !== []): ?>
      <div class="chip-row" style="margin-bottom: 0;">
        <?php foreach ($authors as $a): ?>
          <span class="tag tag-primary"><?= e((string) $a['author']) ?> · <?= e((string) $a['n']) ?> 个工具</span>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <p style="color: var(--c-text-muted);">工具尚未入库，名单将在扫描同步后自动生成。</p>
    <?php endif; ?>
  </div>
</div>

<div class="card about-card">
  <div class="card-head">
    <h2 class="card-title"><?= icon('book-open') ?>版权与素材来源说明</h2>
  </div>
  <div class="card-body about-licenses">
    <?php if (!$hasNotices): ?>
      <p>本站坚持「零 CDN 依赖」：不引用任何外部链接，所有图标、音频、数据均在构建时本地化打包，确保无网条件下工具照常使用。</p>
      <p class="about-licenses-note">第三方素材登记数据暂不可用（assets-src/third-party.json 缺失或损坏）。</p>
    <?php else: ?>
      <p>本站坚持「零 CDN 依赖」：不引用任何外部链接，所有图标、音频、数据均在构建时本地化打包，确保无网条件下工具照常使用。
      以下为本站引用的第三方素材及其许可证，感谢原作者与社区（本表由
      <code>assets-src/third-party.json</code> 自动渲染，新增素材无需手改本页）：</p>

      <?php foreach ($notices['categories'] as $category): ?>
        <?php if ($category['items'] === [] && $category['notes'] === []): continue; endif; ?>
        <h3><?= e($category['title']) ?></h3>
        <?php if ($category['items'] !== []): ?>
          <ul>
            <?php foreach ($category['items'] as $item): ?>
              <li>
                <strong><?= e($item['name']) ?></strong>
                <?php if ($item['source'] !== ''): ?>
                  <?php if (preg_match('#^https?://#', $item['source']) === 1): ?>
                    （<a href="<?= e($item['source']) ?>" target="_blank" rel="noopener nofollow"><?= e(preg_replace('#^https?://(www\.)?#', '', $item['source'])) ?></a><!-- et-allow-external 版权署名链接，非运行时资源 -->）
                  <?php else: ?>
                    （<?= e($item['source']) ?>）
                  <?php endif; ?>
                <?php endif; ?>
                — <strong><?= e($item['license']) ?></strong>。<?= e($item['usage']) ?>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
        <?php foreach ($category['notes'] as $note): ?>
          <p style="font-size: var(--fs-sm); color: var(--c-text-muted);"><?= e($note) ?></p>
        <?php endforeach; ?>
      <?php endforeach; ?>

      <p class="about-licenses-note">
        以上许可证均允许商用；完整登记与上游快照信息见仓库内
        <code>THIRD-PARTY-LICENSES.md</code>（由同一数据源自动生成）。
        若您是相关素材的权利人且认为本站的使用方式不妥，
        <?= $hasFooterContact ? '请通过页脚联系方式与我们沟通。' : '请与我们联系以便及时处理。' ?>
      </p>
    <?php endif; ?>
  </div>
</div>

<div class="card about-card">
  <div class="card-head">
    <h2 class="card-title"><?= icon('users') ?>交流社区</h2>
  </div>
  <div class="card-body">
    <p style="color: var(--c-text-muted);">加入频道 / 群，获取工具更新通知、提需求、和其他老师交流用法（扫码加入）：</p>
    <div class="sponsor-qr-grid" style="max-width: 420px;">
      <div class="sponsor-qr-card">
        <img src="<?= e(asset('img/qq-channel.jpg')) ?>" alt="QQ 频道二维码" loading="lazy" style="width: 100%; height: auto; object-fit: contain; border-radius: var(--r-md);">
        <p class="sponsor-qr-title">QQ 频道</p>
      </div>
      <div class="sponsor-qr-card">
        <img src="<?= e(asset('img/qq-group.jpg')) ?>" alt="QQ 群二维码" loading="lazy" style="width: 100%; height: auto; object-fit: contain; border-radius: var(--r-md);">
        <p class="sponsor-qr-title">QQ 群</p>
      </div>
    </div>
  </div>
</div>

<div class="card about-card">
  <div class="card-head">
    <h2 class="card-title"><?= icon('heart') ?>支持与反馈</h2>
  </div>
  <div class="card-body">
    <p>所有工具<b>永久免费、无广告</b>。如果你觉得它帮到了你的课堂，欢迎<a href="<?= e(url('/sponsor')) ?>">请站长喝一杯奶茶</a>，
    这是项目持续更新的全部动力。</p>
    <p><?php if ($hasFooterContact): ?>
      工具问题、新建工具需求或合作意向，欢迎通过页脚底部的联系方式联系我们。
    <?php else: ?>
      工具问题、新建工具需求或合作意向，欢迎向我们反馈；使用中遇到的一切问题都欢迎指出。
    <?php endif; ?></p>
  </div>
</div>
