<?php
/**
 * 设置：广告位（6 插槽，原始 HTML 仅 admin 可写）
 *
 * @var array<string, array{label: string, enabled: bool, code: string, device: string,
 *       start_date: string, end_date: string}> $slots
 */
use App\Core\Csrf;
use App\Core\View;
?><div class="page-head">
  <h1 class="page-title">广告位</h1>
  <p class="page-desc">
    保存按槽增量写入（未提交的槽位不受影响）。关闭或无代码时前台零痕迹；
    <code>/tool/{id}/use</code> 工具页永不渲染广告（产品底线）。
  </p>
</div>

<?php View::include('partials/admin-settings-tabs', ['activeTab' => 'ads']); ?>

<form method="post" action="<?= e(url('/admin/settings/ads')) ?>">
  <?= Csrf::field() ?>
  <input type="hidden" name="_submit" value="1">

  <?php foreach ($slots as $key => $slot): ?>
    <div class="card" style="margin-bottom: var(--sp-4);">
      <div class="card-head">
        <h2 class="card-title"><?= e($slot['label']) ?></h2>
        <span class="table-sub"><?= e($key) ?></span>
      </div>
      <div class="card-body">
        <input type="hidden" name="ads_<?= e($key) ?>_present" value="1">

        <div class="form-grid">
          <div class="form-group">
            <label class="form-check">
              <input type="checkbox" name="ads_<?= e($key) ?>_enabled" value="1" <?= $slot['enabled'] ? 'checked' : '' ?>>
              <span>启用</span>
            </label>
          </div>
          <div class="form-group">
            <label class="form-label" for="ad-<?= e($key) ?>-device">设备</label>
            <select class="form-input" id="ad-<?= e($key) ?>-device" name="ads_<?= e($key) ?>_device">
              <?php foreach (['all' => '全部', 'desktop' => '仅桌面', 'mobile' => '仅移动'] as $k => $v): ?>
                <option value="<?= e($k) ?>" <?= $slot['device'] === $k ? 'selected' : '' ?>><?= e($v) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label" for="ad-<?= e($key) ?>-start">生效开始（空=不限）</label>
            <input class="form-input" type="date" id="ad-<?= e($key) ?>-start" name="ads_<?= e($key) ?>_start"
                   value="<?= e($slot['start_date']) ?>">
          </div>
          <div class="form-group">
            <label class="form-label" for="ad-<?= e($key) ?>-end">生效结束（到期自动下架）</label>
            <input class="form-input" type="date" id="ad-<?= e($key) ?>-end" name="ads_<?= e($key) ?>_end"
                   value="<?= e($slot['end_date']) ?>">
          </div>
        </div>

        <div class="form-group">
          <label class="form-label" for="ad-<?= e($key) ?>-code">广告代码（原始 HTML/JS，原样输出）</label>
          <textarea class="form-input" id="ad-<?= e($key) ?>-code" name="ads_<?= e($key) ?>_code"
                    rows="4" style="font-family:var(--font-mono, monospace);"><?= e($slot['code']) ?></textarea>
          <div class="form-hint">⚠ 仅限管理员操作；代码原样输出，请勿粘贴不可信来源的内容。设备区分只加 CSS 类，不做服务端分流。</div>
        </div>
      </div>
    </div>
  <?php endforeach; ?>

  <button class="btn btn-primary btn-lg" type="submit"><?= icon('check') ?>保存广告位</button>
</form>
