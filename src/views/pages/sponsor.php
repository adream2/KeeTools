<?php

/**
 * 赞助页（docs/站点运营模块设计.md §六）
 *
 * @var array{title: string, desc: string, cost_note: string, note: string,
 *            wechat_qr: ?string, alipay_qr: ?string, show_footer: bool} $sponsor
 * @var list<array<string, mixed>> $thanks
 */
?><div class="netdisk-wrap">
  <div class="card">
    <div class="card-body" style="text-align:center;">
      <h1 class="netdisk-title"><?= icon('heart') ?><?= e($sponsor['title']) ?></h1>
      <?php if ($sponsor['desc'] !== ''): ?>
        <p class="netdisk-sub"><?= e($sponsor['desc']) ?></p>
      <?php endif; ?>
      <?php if ($sponsor['cost_note'] !== ''): ?>
        <p class="netdisk-sub">站点开销：<?= e($sponsor['cost_note']) ?></p>
      <?php endif; ?>
    </div>
  </div>

  <div class="card">
    <div class="card-body">
      <div class="sponsor-qr-grid">
        <div class="sponsor-qr-card">
          <h2 class="sponsor-qr-title">微信赞助</h2>
          <?php if ($sponsor['wechat_qr'] !== null): ?>
            <img src="<?= e($sponsor['wechat_qr']) ?>" alt="微信收款码" loading="lazy"
                 referrerpolicy="no-referrer">
          <?php else: ?>
            <div class="sponsor-qr-placeholder"><?= icon('qr-code') ?><span>站长未配置微信收款码</span></div>
          <?php endif; ?>
        </div>
        <div class="sponsor-qr-card">
          <h2 class="sponsor-qr-title">支付宝赞助</h2>
          <?php if ($sponsor['alipay_qr'] !== null): ?>
            <img src="<?= e($sponsor['alipay_qr']) ?>" alt="支付宝收款码" loading="lazy"
                 referrerpolicy="no-referrer">
          <?php else: ?>
            <div class="sponsor-qr-placeholder"><?= icon('qr-code') ?><span>站长未配置支付宝收款码</span></div>
          <?php endif; ?>
        </div>
      </div>

      <div class="sponsor-scanned">
        <button class="btn" type="button" id="sponsor-scanned-btn"><?= icon('check') ?>我扫了这码</button>
        <span class="table-sub" id="sponsor-scanned-hint">点击告知站长，仅匿名计数，不收集任何信息</span>
      </div>

      <p class="netdisk-sub" style="text-align:center;"><?= e($sponsor['note']) ?></p>
    </div>
  </div>

  <?php if ($thanks !== []): ?>
    <div class="card">
      <div class="card-head">
        <h2 class="card-title">鸣谢</h2>
      </div>
      <div class="card-body">
        <ul class="sponsor-thanks-list">
          <?php foreach ($thanks as $item): ?>
            <li class="sponsor-thanks-item">
              <strong><?= e((string) $item['name']) ?></strong>
              <?php if ((string) ($item['amount'] ?? '') !== ''): ?>
                <span class="tag tag-success"><?= e((string) $item['amount']) ?></span>
              <?php endif; ?>
              <?php if ((string) ($item['note'] ?? '') !== ''): ?>
                <span class="table-sub"><?= e((string) $item['note']) ?></span>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>
  <?php endif; ?>
</div>

<script>
(function () {
  'use strict';
  var btn = document.getElementById('sponsor-scanned-btn');
  if (!btn) { return; }
  var KEY = 'et_sponsor_clicked';
  var clicked = false;
  try { clicked = localStorage.getItem(KEY) === '1'; } catch (e) {}
  if (clicked) {
    btn.disabled = true;
    var hint = document.getElementById('sponsor-scanned-hint');
    if (hint) { hint.textContent = '今天已经告诉过站长啦，谢谢你的支持'; }
  }
  btn.addEventListener('click', function () {
    try {
      var x = new XMLHttpRequest();
      x.open('POST', '<?= e(url('/api/sponsor/click')) ?>', true);
      x.send();
    } catch (e) {}
    btn.disabled = true;
    var hint = document.getElementById('sponsor-scanned-hint');
    if (hint) { hint.textContent = '已收到，感谢支持！'; }
    try { localStorage.setItem(KEY, '1'); } catch (e) {}
  });
})();
</script>
