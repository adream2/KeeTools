<?php
/**
 * 站点设置
 *
 * @var array<string, array{label: string, value: string, inEnv: bool, inDb: bool, isSwitch: bool}> $items
 */
use App\Core\Csrf;
?><div class="page-head">
  <h1 class="page-title">站点设置</h1>
  <p class="page-desc">保存在数据库 site_config 表；同名项若定义在 .env 中，.env 优先</p>
</div>

<form method="post" action="<?= e(url('/admin/settings')) ?>">
  <?= Csrf::field() ?>
  <input type="hidden" name="_submit" value="1">

  <div class="card" style="margin-bottom: var(--sp-5);">
    <div class="card-body">
      <?php foreach ($items as $key => $item): ?>
        <div class="form-group">
          <label class="form-label" for="set-<?= e($key) ?>">
            <?= e($item['label']) ?>
            <span class="table-sub"><?= e($key) ?></span>
          </label>

          <?php if ($item['isSwitch']): ?>
            <label class="form-check">
              <input type="checkbox" id="set-<?= e($key) ?>" name="<?= e($key) ?>" value="1"
                     <?= $item['value'] === '1' ? 'checked' : '' ?>>
              <span>启用</span>
            </label>
          <?php else: ?>
            <input class="form-input" type="text" id="set-<?= e($key) ?>" name="<?= e($key) ?>"
                   value="<?= e($item['value']) ?>" maxlength="200">
          <?php endif; ?>

          <?php if ($item['inEnv']): ?>
            <div class="form-hint">⚠ 该项已在 .env 中定义，.env 优先于此处保存的值。如需在后台生效，请从 .env 中移除该行。</div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <button class="btn btn-primary btn-lg" type="submit"><?= icon('check') ?>保存设置</button>
</form>
