<?php

/**
 * 关于页
 *
 * @var int $toolCount
 * @var int $subjectCount
 */
$stats = [];
if ($toolCount > 0) {
    $stats[] = ['num' => (string) $toolCount, 'label' => '课堂工具'];
}
if ($subjectCount > 0) {
    $stats[] = ['num' => (string) $subjectCount, 'label' => '学科分类'];
}
$stats[] = ['num' => '100%', 'label' => '免费使用'];
$stats[] = ['num' => '0', 'label' => '外部依赖'];
?><nav class="breadcrumb" aria-label="面包屑">
  <a href="<?= e(url('/')) ?>">首页</a>
  <span class="breadcrumb-sep">/</span>
  <span>关于本站</span>
</nav>

<div class="page-head">
  <h1 class="page-title">关于 <?= e(site_name()) ?></h1>
  <p class="page-desc">面向中小学老师的免费课堂工具集</p>
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
    <h2 class="card-title"><?= icon('info') ?>这是什么</h2>
  </div>
  <div class="card-body">
    <p>每个工具就是一个独立的 HTML 文件：双击打开就能用，断网也能用，投影到教室大屏不糊。
    本站只做工具，不做题库、不做课程、不做作业系统；全部工具免费使用。</p>
    <p>工具可在线使用，也可在工具详情页下载单文件或按学段打包离线合集，方便没有网络的教室。</p>
  </div>
</div>

<div class="card about-card">
  <div class="card-head">
    <h2 class="card-title"><?= icon('book-open') ?>版权与素材来源说明</h2>
  </div>
  <div class="card-body about-licenses">
    <p>本站坚持「零外部依赖」：所有图标、音频、数据均在构建时本地化打包，运行时不请求任何第三方资源。
    以下为本站引用的第三方素材及其许可证，感谢原作者与社区：</p>

    <h3>界面与工具图标</h3>
    <ul>
      <li>
        <strong>Lucide Icons</strong>（<a href="https://lucide.dev" target="_blank" rel="noopener nofollow">lucide.dev</a><!-- et-allow-external 版权署名链接，非运行时资源 -->）
        — ISC License。本站界面图标与工具内图标均取自 Lucide，
        经 Iconify 按需子集化后打包为本地 SVG sprite。
      </li>
      <li>
        <strong>Tabler Icons</strong>（<a href="https://tabler.io/icons" target="_blank" rel="noopener nofollow">tabler.io/icons</a><!-- et-allow-external 版权署名链接，非运行时资源 -->）
        — MIT License。少量界面图标取自 Tabler，同样本地子集化打包。
      </li>
    </ul>

    <h3>音频素材</h3>
    <ul>
      <li>
        <strong>汉语拼音音节真人录音</strong>（拼音表工具点读音频，183 个音节）
        — 由 Chen Wang 录制，来源 <a href="https://github.com/hugolpz/audio-cmn" target="_blank" rel="noopener nofollow">hugolpz/audio-cmn</a><!-- et-allow-external 版权署名链接，非运行时资源 -->，
        以 <strong>CC BY-SA</strong> 许可证提供，本站未做演绎修改，仅作格式转换后内嵌。在此向录制者致谢。
      </li>
    </ul>

    <h3>数据素材</h3>
    <ul>
      <li>
        <strong>汉字笔顺数据</strong>（笔顺工具的逐笔动画，210 字）
        — 基于 <a href="https://github.com/hanzi-writer/hanzi-writer-data" target="_blank" rel="noopener nofollow">hanzi-writer-data</a><!-- et-allow-external 版权署名链接，非运行时资源 -->
        （派生自 Make Me a Hanzi），以 <strong>Arphic Public License</strong> 提供，本站仅使用各笔中位线采样点。
      </li>
    </ul>

    <h3>字体 / 脚本 / 样式</h3>
    <ul>
      <li>不引入任何字体文件，全程使用操作系统自带字体栈（微软雅黑 / 苹方等）。</li>
      <li>不引入任何第三方 JavaScript 库与 CSS 框架，全部为原生实现。</li>
    </ul>

    <p class="about-licenses-note">
      以上许可证（ISC / MIT / CC BY-SA / Arphic Public License）均允许商用；
      完整登记与上游快照信息见仓库内 <code>THIRD-PARTY-LICENSES.md</code>。
      若您是相关素材的权利人且认为本站的使用方式不妥，请通过页脚联系方式与我们沟通。
    </p>
  </div>
</div>

<div class="card about-card">
  <div class="card-head">
    <h2 class="card-title"><?= icon('heart') ?>支持与反馈</h2>
  </div>
  <div class="card-body">
    <p>工具问题、新建工具需求或合作意向，欢迎通过页脚方式联系。
    如果这些工具帮到了你的课堂，欢迎<a href="<?= e(url('/sponsor')) ?>">支持本站</a>，
    帮助我们把更多工具做得更好用。</p>
  </div>
</div>
