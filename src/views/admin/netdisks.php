<?php
/**
 * 网盘链接管理
 *
 * @var bool $dbReady
 * @var list<array<string, mixed>> $links
 * @var list<array<string, mixed>> $tools
 * @var array<string, string> $types
 */
use App\Core\Csrf;
?><div class="page-head">
  <h1 class="page-title">网盘链接管理</h1>
  <p class="page-desc">每工具可配多个网盘；提取码只展示在中间页。建议每个工具至少 2 个网盘互为备份</p>
</div>

<?php if (!$dbReady): ?>
  <div class="alert alert-warning">
    <?= icon('alert-triangle') ?>
    <div>数据库未初始化：请先执行 <code>php scripts/init_db.php</code>。</div>
  </div>
<?php else: ?>
<div class="admin-toolbar">
  <form method="post" action="<?= e(url('/admin/netdisks/check-all')) ?>">
    <?= Csrf::field() ?>
    <button class="btn" type="submit"><?= icon('refresh-cw') ?>批量探活（24h 未检）</button>
  </form>
</div>

<div class="card" style="margin-bottom: var(--sp-5);">
  <div class="card-head">
    <h2 class="card-title">新增链接</h2>
  </div>
  <div class="card-body">
    <form method="post" action="<?= e(url('/admin/netdisks/create')) ?>" class="form-grid">
      <?= Csrf::field() ?>
      <div class="form-group">
        <label class="form-label" for="nd-tool">工具</label>
        <select class="form-input" id="nd-tool" name="tool_id" required>
          <option value="">选择工具…</option>
          <?php foreach ($tools as $tool): ?>
            <option value="<?= e((string) $tool['tool_id']) ?>"><?= e((string) $tool['title']) ?>（<?= e((string) $tool['tool_id']) ?>）</option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label" for="nd-type">网盘类型</label>
        <select class="form-input" id="nd-type" name="netdisk_type">
          <?php foreach ($types as $key => $label): ?>
            <option value="<?= e($key) ?>"><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label" for="nd-url">分享链接</label>
        <input class="form-input" type="url" id="nd-url" name="url" required maxlength="500"
               placeholder="https://pan.baidu.com/s/…"><!-- et-allow-external（仅 placeholder 示例） -->
      </div>
      <div class="form-group">
        <label class="form-label" for="nd-code">提取码</label>
        <input class="form-input" type="text" id="nd-code" name="extract_code" maxlength="20">
      </div>
      <div class="form-group">
        <label class="form-label" for="nd-pkg">包名（可选）</label>
        <input class="form-input" type="text" id="nd-pkg" name="package_name" maxlength="100">
      </div>
      <div class="form-group">
        <button class="btn btn-primary" type="submit"><?= icon('plus') ?>添加</button>
      </div>
    </form>
  </div>
</div>

<?php if ($links === []): ?>
  <div class="empty">
    <div class="empty-title">还没有网盘链接</div>
    <div>先在上方为工具添加网盘链接，详情页才会出现「下载合集包」按钮。</div>
  </div>
<?php else: ?>
<div class="table-wrap">
  <table class="table">
    <thead>
      <tr>
        <th>工具</th>
        <th>类型</th>
        <th>链接 / 提取码</th>
        <th>状态</th>
        <th>探活</th>
        <th>操作</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($links as $link): ?>
        <?php
        $status = (string) ($link['check_status'] ?? '');
        $statusLabels = ['ok' => '存活', 'invalid' => '已失效', 'unknown' => '未知', 'reported' => '用户反馈失效'];
        $statusClass = ['ok' => 'tag-success', 'invalid' => 'tag-danger', 'reported' => 'tag-danger', 'unknown' => 'tag-warning'][$status] ?? '';
        ?>
        <tr class="<?= in_array($status, ['invalid', 'reported'], true) ? 'row-warning' : '' ?>">
          <td>
            <strong><?= e((string) ($link['tool_title'] ?? '')) ?></strong>
            <div class="table-sub"><?= e((string) $link['tool_id']) ?></div>
          </td>
          <td><?= e((string) $link['type_label']) ?></td>
          <td>
            <div class="table-sub" style="word-break:break-all;"><?= e((string) $link['url']) ?></div>
            <?php if ((string) ($link['extract_code'] ?? '') !== ''): ?>
              <span class="tool-meta-mono">提取码 <?= e((string) $link['extract_code']) ?></span>
            <?php endif; ?>
          </td>
          <td>
            <?= $link['is_active'] ? '<span class="tag tag-info">启用</span>' : '<span class="tag">停用</span>' ?>
            <?php if ($status !== ''): ?>
              <span class="tag <?= e($statusClass) ?>"><?= e($statusLabels[$status] ?? $status) ?></span>
            <?php endif; ?>
          </td>
          <td class="table-sub"><?= e((string) ($link['last_checked_at'] ?? '从未')) ?></td>
          <td>
            <div class="table-actions">
              <form method="post" action="<?= e(url('/admin/netdisks/' . (int) $link['id'] . '/check')) ?>">
                <?= Csrf::field() ?>
                <button class="btn btn-sm" type="submit" title="立即探活"><?= icon('refresh-cw') ?>探活</button>
              </form>
              <form method="post" action="<?= e(url('/admin/netdisks/' . (int) $link['id'] . '/toggle')) ?>">
                <?= Csrf::field() ?>
                <button class="btn btn-sm" type="submit"><?= $link['is_active'] ? '停用' : '启用' ?></button>
              </form>
              <form method="post" action="<?= e(url('/admin/netdisks/' . (int) $link['id'] . '/delete')) ?>"
                    data-confirm="确认删除该链接？">
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
<?php endif; ?>
