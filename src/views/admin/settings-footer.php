<?php
/**
 * 设置：页脚与备案
 *
 * @var array<string, array{label: string, value: string, inEnv: bool}> $textItems
 * @var array<string, array{label: string, value: bool}> $switches
 */
use App\Core\Csrf;
use App\Core\View;
?><div class="page-head">
  <h1 class="page-title">页脚与备案</h1>
  <p class="page-desc">只影响页脚区块；保存不会改动其他分区（广告位 / 友链 / 公告等）</p>
</div>

<?php View::include('partials/admin-settings-tabs', ['activeTab' => 'footer']); ?>

<form method="post" action="<?= e(url('/admin/settings/footer')) ?>">
  <?= Csrf::field() ?>
  <input type="hidden" name="_submit" value="1">

  <div class="card" style="margin-bottom: var(--sp-5);">
    <div class="card-body">
      <?php foreach ($textItems as $key => $item): ?>
        <div class="form-group">
          <label class="form-label" for="f-<?= e($key) ?>">
            <?= e($item['label']) ?>
            <span class="table-sub"><?= e($key) ?></span>
          </label>
          <input class="form-input" type="text" id="f-<?= e($key) ?>" name="<?= e($key) ?>"
                 value="<?= e($item['value']) ?>" maxlength="300">
          <?php if ($item['inEnv']): ?>
            <div class="form-hint">⚠ 该键已在 .env 中定义，.env 优先。</div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>

      <?php foreach ($switches as $key => $item): ?>
        <div class="form-group">
          <label class="form-check">
            <input type="checkbox" name="<?= e($key) ?>" value="1" <?= $item['value'] ? 'checked' : '' ?>>
            <span><?= e($item['label']) ?> <span class="table-sub"><?= e($key) ?></span></span>
          </label>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <button class="btn btn-primary btn-lg" type="submit"><?= icon('check') ?>保存页脚设置</button>
</form>
