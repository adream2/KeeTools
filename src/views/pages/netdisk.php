<?php use App\Core\Config;
use App\Core\Csrf;
use App\Core\View;

/**
 * 网盘中间页（/netdisk/{id}?type=）
 *
 * @var array<string, mixed> $tool
 * @var array<string, mixed> $current 当前网盘链接
 * @var list<array<string, mixed>> $netdisks 该工具全部活跃网盘
 */
$toolId = (string) $tool['tool_id'];
$extract = (string) ($current['extract_code'] ?? '');
$size = $current['file_size'] !== null ? (int) $current['file_size'] : null;
$communityQr = trim(Config::string('community_qr_image'));
$communityText = trim(Config::string('community_qr_text'));
?>
<nav class="breadcrumb" aria-label="面包屑">
  <a href="<?= e(url('/')) ?>">首页</a>
  <span class="breadcrumb-sep">/</span>
  <a href="<?= e(url('/tool/' . rawurlencode($toolId))) ?>"><?= e((string) $tool['title']) ?></a>
  <span class="breadcrumb-sep">/</span>
  <span>获取合集包</span>
</nav>

<div class="netdisk-wrap">
  <div class="card netdisk-card">
    <div class="card-body">
      <div class="netdisk-head">
        <span class="netdisk-icon"><?= icon('cloud-download') ?></span>
        <div>
          <h1 class="netdisk-title"><?= e((string) $tool['title']) ?> · 离线合集包</h1>
          <p class="netdisk-sub">
            <?= e((string) $current['type_label']) ?>
            <?php if ((string) ($current['package_name'] ?? '') !== ''): ?>
              · <?= e((string) $current['package_name']) ?>
            <?php endif; ?>
            <?php if ($size !== null && $size > 0): ?>
              · <?= e(number_format($size / 1048576, 1)) ?> MB
            <?php endif; ?>
          </p>
        </div>
      </div>

      <?php if ($extract !== ''): ?>
        <div class="netdisk-code-row">
          <span class="netdisk-code-label">提取码</span>
          <code class="netdisk-code" id="netdisk-code"><?= e($extract) ?></code>
          <button class="btn btn-sm" type="button" data-copy="#netdisk-code"><?= icon('copy') ?>复制</button>
        </div>
        <p class="form-hint">提取码只在当前页面展示，请先复制再前往网盘。</p>
      <?php endif; ?>

      <div class="netdisk-go-row">
        <a class="btn btn-primary btn-lg netdisk-go" data-countdown="10"
           href="<?= e((string) $current['url']) ?>"
           target="_blank" rel="noopener nofollow">
          <?= icon('external-link') ?><span class="netdisk-go-text">获取中…</span>
        </a>
        <noscript>
          <a class="btn btn-lg" href="<?= e((string) $current['url']) ?>"
             target="_blank" rel="noopener nofollow">直接前往 <?= e((string) $current['type_label']) ?></a>
        </noscript>
      </div>

      <details class="netdisk-report">
        <summary class="netdisk-report-toggle">链接打不开或已失效？</summary>
        <form method="post" action="<?= e(url('/netdisk/' . rawurlencode($toolId) . '/report')) ?>" class="netdisk-report-form">
          <?= Csrf::field() ?>
          <input type="hidden" name="type" value="<?= e((string) $current['netdisk_type']) ?>">
          <p class="netdisk-report-text">提交反馈后站长会尽快补上新链接。你也可以先试试本页下方的其他网盘。</p>
          <button class="btn" type="submit"><?= icon('flag') ?>反馈「<?= e((string) $current['type_label']) ?>」链接失效</button>
        </form>
      </details>
    </div>
  </div>

  <?php if (count($netdisks) > 1): ?>
    <div class="card netdisk-alt">
      <div class="card-head">
        <h2 class="card-title">其他网盘渠道</h2>
      </div>
      <div class="card-body">
        <div class="netdisk-alt-list">
          <?php foreach ($netdisks as $item): ?>
            <?php if ((int) $item['id'] === (int) $current['id']) {
                continue;
            } ?>
            <?php $invalid = in_array($item['check_status'], ['invalid', 'reported'], true); ?>
            <a class="netdisk-alt-item<?= $invalid ? ' netdisk-alt-warn' : '' ?>"
               href="<?= e(url('/netdisk/' . rawurlencode($toolId) . '?type=' . rawurlencode((string) $item['netdisk_type']))) ?>">
              <?= icon('cloud') ?><?= e((string) $item['type_label']) ?>
              <?php if ($invalid): ?><span class="tag tag-warning">可能失效</span><?php endif; ?>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <?php if ($communityQr !== '' || Config::bool('sponsor_enabled', false)): ?>
    <div class="card netdisk-cta">
      <div class="card-body netdisk-cta-body">
        <?php if ($communityQr !== ''): ?>
          <div class="netdisk-cta-qr">
            <img src="<?= e($communityQr) ?>" alt="公众号二维码" loading="lazy" referrerpolicy="no-referrer">
            <p><?= e($communityText !== '' ? $communityText : '扫码关注，新工具上线第一时间通知') ?></p>
          </div>
        <?php endif; ?>
        <?php if (Config::bool('sponsor_enabled', false)): ?>
          <div class="netdisk-cta-sponsor">
            <p>本站所有工具免费，靠大家的自愿支持维持服务器与网盘。</p>
            <a class="btn btn-sm" href="<?= e(url('/sponsor')) ?>"><?= icon('heart') ?>支持本站</a>
          </div>
        <?php endif; ?>
      </div>
    </div>
  <?php endif; ?>
</div>
