<?php
/**
 * 分类管理（两级）
 *
 * @var bool   $dbReady
 * @var list<array{id: int, name: string, slug: string, icon: ?string, sort_order: int, count: int, children: list<array<string, mixed>>}> $groups
 */
use App\Core\Csrf;
?><div class="page-head">
  <h1 class="page-title">分类管理</h1>
  <p class="page-desc">一级 = 学段（primary / junior / senior），二级 = 学科（slug 形如 junior-physics）；删除前须先移除关联工具</p>
</div>

<?php if (!$dbReady): ?>
  <div class="alert alert-warning">
    <?= icon('alert-triangle') ?>
    <div>数据库未初始化：请先执行 <code>php scripts/init_db.php</code>。</div>
  </div>
<?php else: ?>

<?php foreach ($groups as $group): ?>
  <div class="card" style="margin-bottom: var(--sp-5);">
    <div class="card-head">
      <h2 class="card-title">
        <?= icon($group['icon'] ?? 'folder-tree') ?><?= e($group['name']) ?>
        <span class="table-sub">/<?= e($group['slug']) ?> · <?= e((string) $group['count']) ?> 个工具</span>
      </h2>
    </div>
    <div class="card-body">

      <div class="cat-row">
        <form method="post" action="<?= e(url('/admin/categories/update')) ?>" class="form-inline-row cat-row-form">
          <?= Csrf::field() ?>
          <input type="hidden" name="id" value="<?= e((string) $group['id']) ?>">
          <label class="cat-tag cat-tag-stage">学段</label>
          <input class="form-input" type="text" name="name" maxlength="20" value="<?= e($group['name']) ?>">
          <span class="table-sub cat-slug"><?= e($group['slug']) ?></span>
          <span class="cat-count"><?= e((string) $group['count']) ?> 工具</span>
          <input class="form-input sort-input" type="number" min="0" name="sort" value="<?= e((string) $group['sort_order']) ?>">
          <button class="btn btn-sm" type="submit"><?= icon('check') ?>保存</button>
        </form>
        <form method="post" action="<?= e(url('/admin/categories/delete')) ?>" class="form-inline-row"
              onsubmit="return confirm('确定删除学段「<?= e($group['name']) ?>」？需先删除其下学科分类。');">
          <?= Csrf::field() ?>
          <input type="hidden" name="id" value="<?= e((string) $group['id']) ?>">
          <button class="btn btn-sm btn-danger" type="submit"><?= icon('trash-2') ?>删除</button>
        </form>
      </div>

      <?php foreach ($group['children'] as $child): ?>
        <div class="cat-row cat-row-child">
          <form method="post" action="<?= e(url('/admin/categories/update')) ?>" class="form-inline-row cat-row-form">
            <?= Csrf::field() ?>
            <input type="hidden" name="id" value="<?= e((string) $child['id']) ?>">
            <input class="form-input" type="text" name="name" maxlength="20" value="<?= e((string) $child['name']) ?>">
            <span class="table-sub cat-slug"><?= e((string) $child['slug']) ?></span>
            <span class="cat-count"><?= e((string) $child['count']) ?> 工具</span>
            <input class="form-input sort-input" type="number" min="0" name="sort" value="<?= e((string) $child['sort_order']) ?>">
            <button class="btn btn-sm" type="submit"><?= icon('check') ?>保存</button>
          </form>
          <form method="post" action="<?= e(url('/admin/categories/delete')) ?>" class="form-inline-row"
                onsubmit="return confirm('确定删除学科「<?= e((string) $child['name']) ?>」？');">
            <?= Csrf::field() ?>
            <input type="hidden" name="id" value="<?= e((string) $child['id']) ?>">
            <button class="btn btn-sm btn-ghost" type="submit"><?= icon('trash-2') ?>删除</button>
          </form>
        </div>
      <?php endforeach; ?>

      <form method="post" action="<?= e(url('/admin/categories/create')) ?>" class="form-inline-row cat-row" style="margin-bottom: var(--sp-0);">
        <?= Csrf::field() ?>
        <input type="hidden" name="parent_id" value="<?= e((string) $group['id']) ?>">
        <input class="form-input" type="text" name="name" placeholder="新学科名，如 计算机" maxlength="20" required>
        <input class="form-input" type="text" name="slug" placeholder="slug，如 junior-it2" maxlength="64" required>
        <button class="btn btn-sm btn-primary" type="submit"><?= icon('plus') ?>添加学科</button>
      </form>

    </div>
  </div>
<?php endforeach; ?>

<div class="card">
  <div class="card-head"><h2 class="card-title">添加学段（一级分类）</h2></div>
  <div class="card-body">
    <form method="post" action="<?= e(url('/admin/categories/create')) ?>" class="form-inline-row">
      <?= Csrf::field() ?>
      <input type="hidden" name="parent_id" value="0">
      <input class="form-input" type="text" name="name" placeholder="学段名，如 幼儿园" maxlength="20" required>
      <input class="form-input" type="text" name="slug" placeholder="slug，如 kindergarten" maxlength="64" required>
      <button class="btn btn-primary" type="submit"><?= icon('plus') ?>添加学段</button>
    </form>
  </div>
</div>

<?php endif; ?>
