<?php
/**
 * 设置：公告
 *
 * @var list<array<string, mixed>> $items
 * @var array<string, array{label: string, value: bool}> $switches
 * @var int $barCount
 */
use App\Core\Csrf;
use App\Core\View;
?><div class="page-head">
  <h1 class="page-title">公告管理</h1>
  <p class="page-desc">关闭记忆用内容指纹：改了文案，旧访客会重新看到；关闭 / 生效期外前台零痕迹</p>
</div>

<?php View::include('partials/admin-settings-tabs', ['activeTab' => 'announce']); ?>

<div class="card" style="margin-bottom: var(--sp-5);">
  <div class="card-head"><h2 class="card-title">通栏设置</h2></div>
  <div class="card-body">
    <form method="post" action="<?= e(url('/admin/settings/announce/save-settings')) ?>">
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
      <div class="form-group">
        <label class="form-label" for="announce-bar-count">通栏条数（1~3）</label>
        <input class="form-input" type="number" id="announce-bar-count" name="announce_bar_count"
               value="<?= e((string) $barCount) ?>" min="1" max="3">
      </div>
      <button class="btn btn-primary" type="submit"><?= icon('check') ?>保存通栏设置</button>
    </form>
  </div>
</div>

<div class="card" style="margin-bottom: var(--sp-5);">
  <div class="card-head"><h2 class="card-title">添加公告</h2></div>
  <div class="card-body">
    <form method="post" action="<?= e(url('/admin/settings/announce/create')) ?>">
      <?= Csrf::field() ?>
      <div class="form-group">
        <label class="form-label" for="an-text">内容</label>
        <input class="form-input" type="text" id="an-text" name="text" required maxlength="200">
      </div>
      <div class="form-grid">
        <div class="form-group">
          <label class="form-label" for="an-type">类型</label>
          <select class="form-input" id="an-type" name="type">
            <option value="info">信息（蓝）</option>
            <option value="update">更新（绿）</option>
            <option value="warning">警告（橙）</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label" for="an-link">链接（可选，过白名单）</label>
          <input class="form-input" type="text" id="an-link" name="link" maxlength="300">
        </div>
        <div class="form-group">
          <label class="form-label" for="an-link-text">链接文案（默认「查看」）</label>
          <input class="form-input" type="text" id="an-link-text" name="link_text" maxlength="20">
        </div>
        <div class="form-group">
          <label class="form-label" for="an-sort">排序（小在前）</label>
          <input class="form-input" type="number" id="an-sort" name="sort_order" value="0" min="0">
        </div>
        <div class="form-group">
          <label class="form-label" for="an-start">生效开始（空=不限）</label>
          <input class="form-input" type="date" id="an-start" name="start_date">
        </div>
        <div class="form-group">
          <label class="form-label" for="an-end">生效结束</label>
          <input class="form-input" type="date" id="an-end" name="end_date">
        </div>
        <div class="form-group">
          <label class="form-check">
            <input type="checkbox" name="pinned" value="1">
            <span>置顶（通栏排序优先）</span>
          </label>
        </div>
        <div class="form-group">
          <label class="form-check">
            <input type="checkbox" name="enabled" value="1" checked>
            <span>启用</span>
          </label>
        </div>
      </div>
      <button class="btn btn-primary" type="submit"><?= icon('plus') ?>添加公告</button>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-head"><h2 class="card-title">全部公告（<?= count($items) ?>）</h2></div>
  <div class="card-body">
    <?php if ($items === []): ?>
      <div class="empty"><div class="empty-title">暂无公告</div></div>
    <?php else: ?>
      <div class="table-wrap">
        <table class="table">
          <thead><tr><th>内容</th><th>类型</th><th>生效期</th><th>状态</th><th>操作</th></tr></thead>
          <tbody>
            <?php foreach ($items as $item): ?>
              <tr>
                <td>
                  <?= e((string) $item['text']) ?>
                  <?php if ((int) $item['pinned'] === 1): ?><span class="tag tag-info">置顶</span><?php endif; ?>
                </td>
                <td><span class="tag"><?= e((string) $item['type']) ?></span></td>
                <td class="table-sub"><?= e((string) ($item['start_date'] ?? '')) ?> ~ <?= e((string) ($item['end_date'] ?? '')) ?></td>
                <td><?= (int) $item['enabled'] === 1 ? '<span class="tag tag-success">启用</span>' : '<span class="tag">停用</span>' ?></td>
                <td>
                  <div class="table-actions">
                    <form method="post" action="<?= e(url('/admin/settings/announce/' . (int) $item['id'] . '/toggle')) ?>">
                      <?= Csrf::field() ?>
                      <button class="btn btn-sm" type="submit"><?= (int) $item['enabled'] === 1 ? '停用' : '启用' ?></button>
                    </form>
                    <form method="post" action="<?= e(url('/admin/settings/announce/' . (int) $item['id'] . '/delete')) ?>"
                          data-confirm="确认删除该公告？">
                      <?= Csrf::field() ?>
                      <button class="btn btn-sm btn-danger" type="submit"><?= icon('trash-2') ?>删除</button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>
