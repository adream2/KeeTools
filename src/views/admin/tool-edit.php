<?php
/**
 * 工具编辑（回写 manifest）
 *
 * @var array<string, mixed> $tool
 * @var bool $writable tools/ 是否可写
 * @var array<string, string> $gradeOptions
 * @var list<string> $subjectOptions
 */
use App\Core\Csrf;

$grades = is_array($tool['grade_range'] ?? null) ? $tool['grade_range'] : [];
$subjects = is_array($tool['subjects'] ?? null) ? $tool['subjects'] : [];
$tags = is_array($tool['tags'] ?? null) ? $tool['tags'] : [];
?><nav class="breadcrumb" aria-label="面包屑">
  <a href="<?= e(url('/admin/tools')) ?>">工具管理</a>
  <span class="breadcrumb-sep">/</span>
  <span><?= e((string) $tool['title']) ?></span>
</nav>

<div class="page-head">
  <h1 class="page-title">编辑工具</h1>
  <p class="page-desc">保存后写入 <code>tools/<?= e((string) $tool['tool_id']) ?>/manifest.json</code> 并同步数据库；manifest 内 <code>updated_at</code> 自动更新为今天</p>
</div>

<?php if (!$writable): ?>
  <div class="alert alert-warning">
    <?= icon('alert-triangle') ?>
    <div><strong>tools/ 目录当前不可写</strong>：本次修改将暂存到数据库覆盖层（降级模式），恢复可写后可在「系统信息」页一键落盘回 manifest 文件。</div>
  </div>
<?php endif; ?>

<form method="post" action="<?= e(url('/admin/tools/' . rawurlencode((string) $tool['tool_id']) . '/update')) ?>">
  <?= Csrf::field() ?>

  <div class="card" style="margin-bottom: var(--sp-5);">
    <div class="card-head"><h2 class="card-title">基本信息</h2></div>
    <div class="card-body">
      <div class="form-row form-row-2">
        <div class="form-group">
          <label class="form-label" for="title">标题 <span class="required">*</span></label>
          <input class="form-input" type="text" id="title" name="title" maxlength="60"
                 value="<?= e((string) $tool['title']) ?>" required>
        </div>
        <div class="form-group">
          <label class="form-label" for="author">作者</label>
          <input class="form-input" type="text" id="author" name="author" maxlength="40"
                 value="<?= e((string) ($tool['author'] ?? '')) ?>">
        </div>
      </div>

      <div class="form-group">
        <label class="form-label" for="description">简介</label>
        <textarea class="form-textarea" id="description" name="description"
                  style="min-height: calc(var(--sp-12));" maxlength="200"><?= e((string) $tool['description']) ?></textarea>
        <div class="form-hint">列表与 SEO 使用的一句话简介，建议不超过 60 字。</div>
      </div>

      <div class="form-group">
        <label class="form-label" for="tags">标签（英文逗号分隔）</label>
        <input class="form-input" type="text" id="tags" name="tags"
               value="<?= e(implode(',', $tags)) ?>">
        <div class="form-hint">同一逻辑不同花样的工具靠标签关联，如「点名，课堂互动」。</div>
      </div>

      <div class="form-row form-row-2">
        <div class="form-group">
          <label class="form-label" for="family">逻辑族 family</label>
          <input class="form-input" type="text" id="family" name="family" maxlength="64"
                 value="<?= e((string) ($tool['family'] ?? '')) ?>">
          <div class="form-hint">留空表示移除该字段。同一算法的不同花样填相同值（小写字母数字连字符）。</div>
        </div>
        <div class="form-group">
          <label class="form-label" for="screen">目标屏幕</label>
          <select class="form-select" id="screen" name="screen">
            <option value="large" <?= $tool['screen'] === 'large' ? 'selected' : '' ?>>大屏 / 投影（large）</option>
            <option value="any" <?= $tool['screen'] === 'any' ? 'selected' : '' ?>>通用（any）</option>
          </select>
        </div>
      </div>
    </div>
  </div>

  <div class="card" style="margin-bottom: var(--sp-5);">
    <div class="card-head"><h2 class="card-title">适用范围</h2></div>
    <div class="card-body">
      <div class="form-group">
        <span class="form-label">适用学段（manifest 规范 §3.1）</span>
        <div class="check-grid">
          <?php foreach ($gradeOptions as $value => $label): ?>
            <label class="form-check">
              <input type="checkbox" name="grade_range[]" value="<?= e($value) ?>"
                     <?= in_array($value, $grades, true) ? 'checked' : '' ?>>
              <span><?= e($label) ?> <code><?= e($value) ?></code></span>
            </label>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="form-group">
        <span class="form-label">适用学科（manifest 规范 §3.2）</span>
        <div class="check-grid">
          <?php foreach ($subjectOptions as $subject): ?>
            <label class="form-check">
              <input type="checkbox" name="subjects[]" value="<?= e($subject) ?>"
                     <?= in_array($subject, $subjects, true) ? 'checked' : '' ?>>
              <span><?= e($subject) ?></span>
            </label>
          <?php endforeach; ?>
        </div>
        <div class="form-hint">通用类工具只勾「通用」，将同时归入三个学段的一级分类。</div>
      </div>
    </div>
  </div>

  <div class="card" style="margin-bottom: var(--sp-5);">
    <div class="card-head"><h2 class="card-title">其它</h2></div>
    <div class="card-body">
      <div class="form-row form-row-2">
        <div class="form-group">
          <label class="form-label" for="license">许可证</label>
          <input class="form-input" type="text" id="license" name="license" maxlength="30"
                 value="<?= e((string) ($tool['license'] ?? 'free')) ?>">
          <div class="form-hint">默认 free（免费使用）。</div>
        </div>
        <div class="form-group">
          <span class="form-label">统计</span>
          <label class="form-check">
            <input type="checkbox" name="stats_enabled" value="1"
                   <?= $tool['stats_enabled'] ? 'checked' : '' ?>>
            <span>纳入使用统计</span>
          </label>
        </div>
      </div>
    </div>
  </div>

  <div class="admin-toolbar">
    <button class="btn btn-primary btn-lg" type="submit"><?= icon('check') ?>保存并回写 manifest</button>
    <a class="btn btn-ghost" href="<?= e(url('/admin/tools')) ?>">取消</a>
  </div>
</form>
