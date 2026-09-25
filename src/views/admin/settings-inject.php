<?php
/**
 * 设置：代码注入（head / body 尾部，原始 HTML 仅 admin 可写）
 *
 * @var array<string, array{label: string, value: string, inEnv: bool}> $textItems
 * @var array<string, array{label: string, value: bool}> $switches
 */
use App\Core\Csrf;
use App\Core\View;
?><div class="page-head">
  <h1 class="page-title">代码注入</h1>
  <p class="page-desc">
    向全站页面的 <code>&lt;head&gt;</code> 与 <code>&lt;body&gt;</code> 尾部注入自定义代码，
    用于统计脚本、meta 标签等；关闭或留空时前台零痕迹。
  </p>
</div>

<?php View::include('partials/admin-settings-tabs', ['activeTab' => 'inject']); ?>

<form method="post" action="<?= e(url('/admin/settings/inject')) ?>">
  <?= Csrf::field() ?>
  <input type="hidden" name="_submit" value="1">

  <?php foreach ($switches as $key => $item): ?>
    <div class="form-group">
      <label class="form-check">
        <input type="checkbox" name="<?= e($key) ?>" value="1" <?= $item['value'] ? 'checked' : '' ?>>
        <span><?= e($item['label']) ?> <span class="table-sub"><?= e($key) ?></span></span>
      </label>
    </div>
  <?php endforeach; ?>

  <?php foreach ($textItems as $key => $item): ?>
    <div class="card" style="margin-bottom: var(--sp-4);">
      <div class="card-head">
        <h2 class="card-title"><?= e($item['label']) ?></h2>
        <span class="table-sub"><?= e($key) ?></span>
      </div>
      <div class="card-body">
        <div class="form-group">
          <label class="form-label" for="inject-<?= e($key) ?>">注入代码（原始 HTML/JS，原样输出）</label>
          <textarea class="form-input" id="inject-<?= e($key) ?>" name="<?= e($key) ?>"
                    rows="8" style="font-family:var(--font-mono, monospace);"
                    placeholder="<?= $key === 'inject_head_code'
                        ? '&lt;script&gt;/* 例：统计脚本 */&lt;/script&gt;'
                        : '&lt;script&gt;/* 例：浮动客服 / 回到顶部 */&lt;/script&gt;' ?>"><?= e($item['value']) ?></textarea>
          <div class="form-hint">
          ⚠ 仅限管理员操作；代码原样输出，请勿粘贴不可信来源的内容。上限 64KB。工具在线使用页（/tool/{id}/use）为独立自包含页面，不经过本站布局，不注入。
            <?php if ($item['inEnv']): ?>
              ⚠ 该键已在 .env 中定义，.env 优先。
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  <?php endforeach; ?>

  <button class="btn btn-primary btn-lg" type="submit"><?= icon('check') ?>保存代码注入</button>
</form>
