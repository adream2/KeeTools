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
  <p class="page-desc">
    工具放入 <code>tools/</code> 目录即自动上架（manifest 有改动也会自动同步）；
    此处只调整推荐 / 上架 / 排序
  </p>
</div>

<?php if (!$dbReady): ?>
  <div class="alert alert-warning">
    <?= icon('alert-triangle') ?>
    <div>数据库未初始化：请先执行 <code>php scripts/init_db.php</code>。</div>
  </div>
<?php elseif ($tools === []): ?>
  <div class="empty">
    <div class="empty-title">还没有工具</div>
    <div>把工具目录放进 <code>tools/</code>，刷新本页即自动上架。</div>
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
