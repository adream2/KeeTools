<?php
/**
 * 设置：赞助
 *
 * @var array<string, array{label: string, value: string, inEnv: bool}> $textItems
 * @var array<string, array{label: string, value: bool}> $switches
 * @var list<array<string, mixed>> $thanks
 */
use App\Core\Csrf;
use App\Core\View;
?><div class="page-head">
  <h1 class="page-title">赞助设置</h1>
  <p class="page-desc">总开关关闭时 /sponsor/ 返回 404，页脚与首页入口全部消失（零痕迹）</p>
</div>

<?php View::include('partials/admin-settings-tabs', ['activeTab' => 'sponsor']); ?>

<form method="post" action="<?= e(url('/admin/settings/sponsor')) ?>">
  <?= Csrf::field() ?>
  <input type="hidden" name="_submit" value="1">

  <div class="card" style="margin-bottom: var(--sp-5);">
    <div class="card-body">
      <?php foreach ($switches as $key => $item): ?>
        <div class="form-group">
          <label class="form-check">
            <input type="checkbox" name="<?= e($key) ?>" value="1" <?= $item['value'] ? 'checked' : '' ?>>
            <span><?= e($item['label']) ?> <span class="table-sub"><?= e($key) ?></span></span>
          </label>
        </div>
      <?php endforeach; ?>

      <hr style="border: none; border-top: var(--bw-1) solid var(--c-border); margin: var(--sp-4) 0;">

      <?php foreach ($textItems as $key => $item): ?>
        <div class="form-group">
          <label class="form-label" for="sp-<?= e($key) ?>">
            <?= e($item['label']) ?>
            <span class="table-sub"><?= e($key) ?></span>
          </label>
          <input class="form-input" type="text" id="sp-<?= e($key) ?>" name="<?= e($key) ?>"
                 value="<?= e($item['value']) ?>" maxlength="300">
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <button class="btn btn-primary btn-lg" type="submit"><?= icon('check') ?>保存赞助设置</button>
</form>

<div class="card" style="margin-top: var(--sp-5);">
  <div class="card-head"><h2 class="card-title">鸣谢管理</h2></div>
  <div class="card-body">
    <form method="post" action="<?= e(url('/admin/settings/sponsor/thanks/create')) ?>" class="form-grid"
          style="margin-bottom: var(--sp-4);">
      <?= Csrf::field() ?>
      <div class="form-group">
        <label class="form-label" for="th-name">留名（≤20 字）</label>
        <input class="form-input" type="text" id="th-name" name="name" required maxlength="20">
      </div>
      <div class="form-group">
        <label class="form-label" for="th-amount">金额（可选展示）</label>
        <input class="form-input" type="text" id="th-amount" name="amount" maxlength="10" placeholder="¥10">
      </div>
      <div class="form-group">
        <label class="form-label" for="th-note">一句话（≤60 字）</label>
        <input class="form-input" type="text" id="th-note" name="note" maxlength="60">
      </div>
      <div class="form-group">
        <button class="btn btn-primary" type="submit"><?= icon('plus') ?>添加</button>
      </div>
    </form>

    <?php if ($thanks === []): ?>
      <div class="empty"><div class="empty-title">暂无鸣谢记录</div></div>
    <?php else: ?>
      <div class="table-wrap">
        <table class="table">
          <thead><tr><th>留名</th><th>金额</th><th>留言</th><th>时间</th><th>操作</th></tr></thead>
          <tbody>
            <?php foreach ($thanks as $item): ?>
              <tr>
                <td><?= e((string) $item['name']) ?></td>
                <td><?= e((string) ($item['amount'] ?? '')) ?></td>
                <td class="table-sub"><?= e((string) $item['note']) ?></td>
                <td class="table-sub"><?= e((string) $item['created_at']) ?></td>
                <td>
                  <form method="post" action="<?= e(url('/admin/settings/sponsor/thanks/' . (int) $item['id'] . '/delete')) ?>"
                        data-confirm="确认删除该鸣谢？">
                    <?= Csrf::field() ?>
                    <button class="btn btn-sm btn-danger" type="submit"><?= icon('trash-2') ?>删除</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>
