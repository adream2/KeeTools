<?php
/**
 * 设置：导航与页脚
 *
 * @var array<string, array{label: string, value: string, inEnv: bool}> $textItems
 * @var array<string, array{label: string, value: bool}> $switches
 * @var string $headerNavText 顶部导航回显文本（每行 名称 | URL）
 * @var string $footerNavText 页脚快捷导航回显文本
 * @var string $friendApplyUrl 申请友链链接
 */
use App\Core\Csrf;
use App\Core\View;
?><div class="page-head">
  <h1 class="page-title">导航与页脚</h1>
  <p class="page-desc">顶部导航 / 页脚快捷导航 / 友链申请入口 / 页脚区块；保存不会改动其他分区（广告位 / 友链 / 公告等）</p>
</div>

<?php View::include('partials/admin-settings-tabs', ['activeTab' => 'footer']); ?>

<form method="post" action="<?= e(url('/admin/settings/footer')) ?>">
  <?= Csrf::field() ?>
  <input type="hidden" name="_submit" value="1">

  <div class="card" style="margin-bottom: var(--sp-4);">
    <div class="card-head"><h2 class="card-title">顶部导航菜单</h2></div>
    <div class="card-body">
      <div class="form-group">
        <label class="form-label" for="nav-header">主导航（每行一条：名称 | URL，留空使用默认「首页 / 全部工具」）</label>
        <textarea class="form-input" id="nav-header" name="header_nav" rows="4"
                  placeholder="首页 | /&#10;全部工具 | /tools&#10;资源导航 | https://example.com"><?= e($headerNavText) ?></textarea>
        <div class="form-hint">URL 支持 http(s) 外链与站内 / 路径；非法行保存时自动跳过。</div>
      </div>
    </div>
  </div>

  <div class="card" style="margin-bottom: var(--sp-4);">
    <div class="card-head"><h2 class="card-title">页脚快捷导航</h2></div>
    <div class="card-body">
      <div class="form-group">
        <label class="form-label" for="nav-footer">快捷导航（每行一条：名称 | URL，留空使用默认「全部工具 / 支持本站 / 后台」）</label>
        <textarea class="form-input" id="nav-footer" name="footer_nav" rows="4"
                  placeholder="全部工具 | /tools&#10;关于本站 | /about"><?= e($footerNavText) ?></textarea>
      </div>
      <div class="form-group">
        <label class="form-label" for="fl-apply">申请友链入口链接（留空 = 页脚不显示「申请友链」）</label>
        <input class="form-input" type="text" id="fl-apply" name="friend_link_apply_url"
               value="<?= e($friendApplyUrl) ?>" maxlength="300"
               placeholder="/friend-links 或 https://…">
        <div class="form-hint">可指向站内说明页或外链表单；显示在页脚「友情链接」标题旁。</div>
      </div>
    </div>
  </div>

  <div class="card" style="margin-bottom: var(--sp-5);">
    <div class="card-head"><h2 class="card-title">页脚区块</h2></div>
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

  <button class="btn btn-primary btn-lg" type="submit"><?= icon('check') ?>保存导航与页脚设置</button>
</form>
