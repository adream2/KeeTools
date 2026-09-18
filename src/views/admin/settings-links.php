<?php
/**
 * 设置：友链（首页 / 内页投放面）
 *
 * @var list<array<string, mixed>> $links
 */
use App\Core\Csrf;
use App\Core\View;
?><div class="page-head">
  <h1 class="page-title">友链管理</h1>
  <p class="page-desc">首页友链只在首页页脚渲染（权重最高）；内页友链在其余页面页脚渲染。URL 非法的条目前台不输出</p>
</div>

<?php View::include('partials/admin-settings-tabs', ['activeTab' => 'links']); ?>

<div class="card" style="margin-bottom: var(--sp-5);">
  <div class="card-head"><h2 class="card-title">添加友链</h2></div>
  <div class="card-body">
    <form method="post" action="<?= e(url('/admin/settings/links/create')) ?>" class="form-grid">
      <?= Csrf::field() ?>
      <div class="form-group">
        <label class="form-label" for="fl-name">名称（≤20 字）</label>
        <input class="form-input" type="text" id="fl-name" name="name" required maxlength="20">
      </div>
      <div class="form-group">
        <label class="form-label" for="fl-url">URL</label>
        <input class="form-input" type="text" id="fl-url" name="url" required maxlength="300"
               placeholder="https://…（站内可填 /path）">
      </div>
      <div class="form-group">
        <label class="form-label" for="fl-placement">投放面</label>
        <select class="form-input" id="fl-placement" name="placement">
          <option value="all">两处（首页 + 内页）</option>
          <option value="home">仅首页</option>
          <option value="sub">仅内页</option>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label" for="fl-sort">排序</label>
        <input class="form-input" type="number" id="fl-sort" name="sort_order" value="0" min="0">
      </div>
      <div class="form-group">
        <label class="form-check">
          <input type="checkbox" name="nofollow" value="1">
          <span>nofollow（内页友链建议勾选）</span>
        </label>
      </div>
      <div class="form-group">
        <button class="btn btn-primary" type="submit"><?= icon('plus') ?>添加</button>
      </div>
    </form>
  </div>
</div>

<div class="card" style="margin-bottom: var(--sp-5);">
  <div class="card-head"><h2 class="card-title">批量添加</h2></div>
  <div class="card-body">
    <form method="post" action="<?= e(url('/admin/settings/links/batch')) ?>">
      <?= Csrf::field() ?>
      <div class="form-group">
        <label class="form-label" for="fl-batch">每行一条：名称 | URL | nofollow(0/1)</label>
        <textarea class="form-input" id="fl-batch" name="batch" rows="6"
                  placeholder="示例站点 | https://example.com | 1"></textarea>
      </div>
      <button class="btn" type="submit"><?= icon('plus') ?>批量导入</button>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-head"><h2 class="card-title">全部友链（<?= count($links) ?>）</h2></div>
  <div class="card-body">
    <?php if ($links === []): ?>
      <div class="empty"><div class="empty-title">暂无友链</div></div>
    <?php else: ?>
      <div class="table-wrap">
        <table class="table">
          <thead>
            <tr><th>名称</th><th>URL</th><th>投放面</th><th>nofollow</th><th>启用</th><th>排序</th><th>操作</th></tr>
          </thead>
          <tbody>
            <?php foreach ($links as $link): ?>
              <tr class="<?= $link['url_valid'] ? '' : 'row-warning' ?>">
                <td><input class="form-input" type="text" form="fl-form-<?= (int) $link['id'] ?>"
                           name="name" value="<?= e((string) $link['name']) ?>" maxlength="20"></td>
                <td>
                  <input class="form-input" type="text" form="fl-form-<?= (int) $link['id'] ?>"
                         name="url" value="<?= e((string) $link['url']) ?>" maxlength="300" style="min-width: 220px;">
                  <?php if (!$link['url_valid']): ?>
                    <span class="tag tag-danger">URL 非法，前台已跳过</span>
                  <?php endif; ?>
                </td>
                <td>
                  <select class="form-input" form="fl-form-<?= (int) $link['id'] ?>" name="placement">
                    <?php foreach (['all' => '两处', 'home' => '仅首页', 'sub' => '仅内页'] as $k => $v): ?>
                      <option value="<?= e($k) ?>" <?= $link['placement'] === $k ? 'selected' : '' ?>><?= e($v) ?></option>
                    <?php endforeach; ?>
                  </select>
                </td>
                <td><input type="checkbox" form="fl-form-<?= (int) $link['id'] ?>" name="nofollow" value="1" <?= $link['nofollow'] ? 'checked' : '' ?>></td>
                <td><input type="checkbox" form="fl-form-<?= (int) $link['id'] ?>" name="enabled" value="1" <?= $link['enabled'] ? 'checked' : '' ?>></td>
                <td><input class="form-input sort-input" type="number" form="fl-form-<?= (int) $link['id'] ?>" name="sort_order" value="<?= e((string) (int) $link['sort_order']) ?>" min="0"></td>
                <td>
                  <div class="table-actions">
                    <button class="btn btn-sm" type="submit" form="fl-form-<?= (int) $link['id'] ?>"><?= icon('check') ?>保存</button>
                    <form method="post" action="<?= e(url('/admin/settings/links/' . (int) $link['id'] . '/delete')) ?>"
                          data-confirm="确认删除该友链？">
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
      <?php foreach ($links as $link): ?>
        <form id="fl-form-<?= (int) $link['id'] ?>" method="post"
              action="<?= e(url('/admin/settings/links/' . (int) $link['id'] . '/update')) ?>" style="display:none;">
          <?= Csrf::field() ?>
        </form>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>
