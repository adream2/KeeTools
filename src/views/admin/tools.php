<?php
/**
 * 工具管理列表
 *
 * @var bool   $dbReady
 * @var list<array<string, mixed>> $tools
 */
use App\Core\Csrf;
?><div class="page-head">
  <h1 class="page-title">工具管理</h1>
  <p class="page-desc">扫描 tools/ 目录同步入库；编辑元数据会回写 manifest.json</p>
</div>

<div class="admin-toolbar">
  <form method="post" action="<?= e(url('/admin/tools/scan')) ?>">
    <?= Csrf::field() ?>
    <button class="btn btn-primary" type="submit"><?= icon('refresh-cw') ?>扫描同步</button>
  </form>
</div>

<?php if (!$dbReady): ?>
  <div class="alert alert-warning">
    <?= icon('alert-triangle') ?>
    <div>数据库未初始化：请先执行 <code>php scripts/init_db.php</code>。</div>
  </div>
<?php elseif ($tools === []): ?>
  <div class="empty">
    <div class="empty-title">还没有工具入库</div>
    <div>把工具目录放进 <code>tools/</code> 后，点击上方「扫描同步」。</div>
  </div>
<?php else: ?>
<form method="post" action="<?= e(url('/admin/tools/batch')) ?>">
  <?= Csrf::field() ?>
  <div class="table-wrap">
    <table class="table">
      <thead>
        <tr>
          <th>工具</th>
          <th>版本</th>
          <th>类型</th>
          <th>推荐</th>
          <th>上架</th>
          <th>排序</th>
          <th>操作</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($tools as $tool): ?>
          <?php $toolId = (string) $tool['tool_id']; ?>
          <tr class="<?= (int) $tool['meta_mismatch'] === 1 ? 'row-warning' : '' ?>">
            <td>
              <strong><?= e((string) $tool['title']) ?></strong>
              <div class="table-sub"><?= e($toolId) ?></div>
            </td>
            <td>
              <span class="tool-meta-mono">v<?= e((string) $tool['version']) ?></span>
              <?php if ((int) $tool['meta_mismatch'] === 1): ?>
                <div><?= icon('alert-triangle') ?><span class="tag tag-danger">版本不一致</span></div>
              <?php endif; ?>
            </td>
            <td><?= e(tool_type_label((string) $tool['type'])) ?></td>
            <td>
              <input type="checkbox" name="featured[<?= e($toolId) ?>]"
                     <?= (int) $tool['is_featured'] === 1 ? 'checked' : '' ?>>
            </td>
            <td>
              <input type="checkbox" name="published[<?= e($toolId) ?>]"
                     <?= (int) $tool['is_published'] === 1 ? 'checked' : '' ?>>
            </td>
            <td>
              <input class="form-input sort-input" type="number" min="0"
                     name="sort[<?= e($toolId) ?>]" value="<?= e((string) (int) $tool['sort_order']) ?>">
            </td>
            <td>
              <div class="table-actions">
                <a class="btn btn-sm"
                   href="<?= e(url('/admin/tools/' . rawurlencode($toolId) . '/edit')) ?>">
                  <?= icon('pencil') ?>编辑
                </a>
                <a class="btn btn-sm btn-ghost" target="_blank" rel="noopener"
                   href="<?= e(url('/tool/' . rawurlencode($toolId))) ?>">
                  <?= icon('external-link') ?>前台
                </a>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="admin-toolbar">
    <button class="btn btn-primary" type="submit"><?= icon('check') ?>保存推荐 / 上架 / 排序</button>
  </div>
</form>
<?php endif; ?>
