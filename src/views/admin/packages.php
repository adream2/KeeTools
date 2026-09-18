<?php
/**
 * 离线包打包（P3）
 *
 * @var bool $dbReady
 * @var list<array{tool_id: string, title: string, version: string, bytes: int}> $tools
 * @var list<array<string, mixed>> $packages
 * @var array<string, mixed>|null $task
 * @var bool $zipReady
 * @var int $maxSizeMb
 */
use App\Core\Csrf;
?><div class="page-head">
  <h1 class="page-title">离线包打包</h1>
  <p class="page-desc">把工具集打包成可网盘分发的离线合集包（内含本地导航门户）。产物存于 packages/ 目录，不入 git</p>
</div>

<?php if (!$dbReady): ?>
  <div class="alert alert-warning">
    <?= icon('alert-triangle') ?>
    <div>数据库未初始化：请先执行 <code>php scripts/init_db.php</code>。</div>
  </div>
<?php else: ?>

<?php if (!$zipReady): ?>
  <div class="alert alert-danger">
    <?= icon('alert-triangle') ?>
    <div><strong>服务器 PHP 未启用 zip 扩展（ZipArchive）</strong>，无法打包。请联系主机商启用 <code>ext-zip</code> 后再试。</div>
  </div>
<?php endif; ?>

<div class="card" style="margin-bottom: var(--sp-5);">
  <div class="card-head">
    <h2 class="card-title">新建打包任务</h2>
  </div>
  <div class="card-body">
    <form id="pkg-form" method="post" action="<?= e(url('/admin/package/create')) ?>">
      <?= Csrf::field() ?>
      <div class="form-grid">
        <div class="form-group">
          <label class="form-label" for="pkg-slug">包标识（slug）</label>
          <input class="form-input" type="text" id="pkg-slug" name="slug" required
                 value="keetools" pattern="[a-zA-Z0-9-]{1,64}" maxlength="64">
          <div class="table-sub">产物名 = 包标识-版本.zip，如 keetools-1.0.0.zip</div>
        </div>
        <div class="form-group">
          <label class="form-label" for="pkg-version">包版本</label>
          <input class="form-input" type="text" id="pkg-version" name="version" required
                 value="1.0.0" pattern="\d+\.\d+\.\d+" maxlength="16">
        </div>
        <div class="form-group">
          <span class="form-label">导航门户</span>
          <label class="form-check">
            <input type="checkbox" name="with_portal" value="1" checked>
            包含本地导航门户 index.html（推荐，品牌回流主力触点）
          </label>
        </div>
      </div>

      <div class="form-group">
        <span class="form-label">选择工具（<?= e((string) count($tools)) ?> 个可打包）</span>
        <div class="pkg-tool-list">
          <?php foreach ($tools as $tool): ?>
            <label class="form-check pkg-tool-item">
              <input type="checkbox" name="tool_ids[]" class="pkg-tool-check"
                     value="<?= e($tool['tool_id']) ?>"
                     data-size="<?= e((string) $tool['bytes']) ?>">
              <span class="pkg-tool-name"><?= e($tool['title']) ?></span>
              <span class="table-sub"><?= e($tool['tool_id']) ?> · v<?= e($tool['version']) ?> · <?= e(number_format($tool['bytes'] / 1024, 0)) ?> KB · 更新 <?= e(substr((string) $tool['updated_at'], 0, 10)) ?></span>
            </label>
          <?php endforeach; ?>
          <?php if ($tools === []): ?>
            <div class="empty"><div class="empty-title">暂无可打包工具</div><div>工具须已上架、单文件且支持离线。</div></div>
          <?php endif; ?>
        </div>
      </div>

      <div class="pkg-estimate">
        已选 <strong id="pkg-count">0</strong> 个工具，原始体积约
        <strong id="pkg-size">0 KB</strong>
        （单包上限 <?= e((string) $maxSizeMb) ?>MB，超限须拆包）
        <div class="table-sub" style="margin-top: var(--sp-2);">
          增量更新：在「历史产物」点「勾选变更」，只打包相对该包有更新 / 新增的工具，
          产物建议用 包标识-update 命名，网盘补发时下载量最小。
        </div>
      </div>

      <button class="btn btn-primary" type="submit" <?= $zipReady ? '' : 'disabled' ?>>
        <?= icon('package') ?>创建打包任务
      </button>
    </form>
  </div>
</div>

<div class="card" style="margin-bottom: var(--sp-5);">
  <div class="card-head">
    <h2 class="card-title">任务进度</h2>
  </div>
  <div class="card-body">
    <div id="pkg-progress" class="pkg-progress-wrap">
      <div class="pkg-progress" role="progressbar" aria-valuemin="0" aria-valuemax="100">
        <div class="pkg-progress-bar" id="pkg-bar" style="width:0%"></div>
      </div>
      <div class="pkg-progress-text" id="pkg-text">暂无任务</div>
    </div>
    <div id="pkg-result" style="display:none; margin-top: var(--sp-4);"></div>
  </div>
</div>

<div class="card">
  <div class="card-head">
    <h2 class="card-title">历史产物</h2>
  </div>
  <div class="card-body">
    <?php if ($packages === []): ?>
      <div class="empty">
        <div class="empty-title">还没有产物</div>
        <div>打包完成后 zip 会出现在 packages/ 目录，另有同名的「网盘上传清单」文本。</div>
      </div>
    <?php else: ?>
      <div class="table-wrap">
        <table class="table">
          <thead>
            <tr>
              <th>产物</th>
              <th>工具数</th>
              <th>体积</th>
              <th>SHA256</th>
              <th>生成时间</th>
              <th>操作</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($packages as $pkg): ?>
              <tr>
                <td>
                  <strong class="tool-meta-mono"><?= e((string) $pkg['file_name']) ?></strong>
                  <?php if (!$pkg['exists']): ?>
                    <span class="tag tag-danger">文件缺失</span>
                  <?php endif; ?>
                </td>
                <td><?= e((string) $pkg['tool_count']) ?></td>
                <td><?= e(number_format($pkg['size_bytes'] / 1048576, 2)) ?> MB</td>
                <td class="table-sub tool-meta-mono"><?= e(substr((string) $pkg['sha256'], 0, 12)) ?>…</td>
                <td class="table-sub"><?= e((string) $pkg['created_at']) ?></td>
                <td>
                  <div class="table-actions">
                    <?php if ($pkg['exists']): ?>
                      <a class="btn btn-sm" href="<?= e(url('/admin/package/' . (int) $pkg['id'] . '/download')) ?>">
                        <?= icon('download') ?>下载
                      </a>
                    <?php endif; ?>
                    <?php if (($pkg['changed'] ?? []) !== []): ?>
                      <button class="btn btn-sm pkg-diff-btn" type="button"
                              data-changed="<?= e(json_encode($pkg['changed'], JSON_UNESCAPED_UNICODE)) ?>"
                              data-slug="<?= e((string) $pkg['slug']) ?>-update"
                              data-version="<?= e((string) $pkg['version']) ?>"
                              title="相对此包的变更：<?= e(implode('、', $pkg['changed'])) ?>">
                        <?= icon('refresh-cw') ?>勾选变更（<?= e((string) count($pkg['changed'])) ?>）
                      </button>
                    <?php endif; ?>
                    <form method="post" action="<?= e(url('/admin/package/' . (int) $pkg['id'] . '/regen')) ?>">
                      <?= Csrf::field() ?>
                      <button class="btn btn-sm" type="submit"><?= icon('refresh-cw') ?>重新生成</button>
                    </form>
                    <form method="post" action="<?= e(url('/admin/package/' . (int) $pkg['id'] . '/delete')) ?>"
                          data-confirm="确认删除该产物？磁盘文件会一并删除。">
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
      <p class="table-sub" style="margin-top: var(--sp-3);">
        <?= icon('cloud') ?>打包完成后请把 zip 上传网盘，并到
        <a href="<?= e(url('/admin/netdisks')) ?>">网盘管理</a> 回填链接与提取码（提取码只放中间页，不放包内）。
      </p>
    <?php endif; ?>
  </div>
</div>

<script>
(function () {
    'use strict';

    // ── 实时预估包体 ──
    var form = document.getElementById('pkg-form');
    if (!form) { return; }
    var checks = form.querySelectorAll('.pkg-tool-check');
    var countEl = document.getElementById('pkg-count');
    var sizeEl = document.getElementById('pkg-size');

    function fmtSize(bytes) {
        if (bytes >= 1048576) { return (bytes / 1048576).toFixed(2) + ' MB'; }
        return Math.round(bytes / 1024) + ' KB';
    }
    function refreshEstimate() {
        var count = 0, bytes = 0;
        for (var i = 0; i < checks.length; i++) {
            if (checks[i].checked) {
                count++;
                bytes += parseInt(checks[i].getAttribute('data-size'), 10) || 0;
            }
        }
        countEl.textContent = String(count);
        sizeEl.textContent = fmtSize(bytes);
    }
    for (var i = 0; i < checks.length; i++) {
        checks[i].addEventListener('change', refreshEstimate);
    }
    refreshEstimate();

    // ── 增量包辅助：按历史产物的变更清单一键勾选 ──
    Array.prototype.forEach.call(document.querySelectorAll('.pkg-diff-btn'), function (btn) {
        btn.addEventListener('click', function () {
            var changed;
            try { changed = JSON.parse(btn.getAttribute('data-changed')) || []; } catch (e) { changed = []; }
            for (var i = 0; i < checks.length; i++) {
                checks[i].checked = changed.indexOf(checks[i].value) !== -1;
            }
            document.getElementById('pkg-slug').value = btn.getAttribute('data-slug') || '';
            document.getElementById('pkg-version').value = btn.getAttribute('data-version') || '';
            refreshEstimate();
            form.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    });

    // ── 任务进度轮询（status 端点同时惰性消费 pending 任务）──
    var bar = document.getElementById('pkg-bar');
    var text = document.getElementById('pkg-text');
    var result = document.getElementById('pkg-result');
    var timer = null;

    function renderTask(task) {
        if (!task) { return true; }
        bar.style.width = task.progress + '%';
        bar.parentElement.setAttribute('aria-valuenow', String(task.progress));
        text.textContent = '#' + task.id + ' ' + task.slug + ' v' + task.version
            + ' — ' + (task.stage || task.status) + (task.message ? '：' + task.message : '');

        if (task.status === 'done') { showResult(true, task.message); return false; }
        if (task.status === 'failed') { showResult(false, task.message); return false; }
        return true;
    }

    function showResult(ok, message) {
        result.style.display = '';
        result.className = 'alert alert-' + (ok ? 'success' : 'danger');
        result.textContent = message || (ok ? '打包完成' : '打包失败');
        if (ok) {
            // 打包完成后引导回填网盘链接（P1 联动）
            var tip = document.createElement('div');
            tip.className = 'table-sub';
            tip.style.marginTop = 'var(--sp-2)';
            tip.innerHTML = '下一步：把 zip 上传网盘后，到 <a href="<?= e(url('/admin/netdisks')) ?>">后台 → 网盘管理</a> 为对应工具回填链接与提取码。';
            result.appendChild(tip);
        }
    }

    function scheduleNext(delay) {
        if (timer !== null) { return; }
        timer = window.setTimeout(function () { timer = null; poll(); }, delay);
    }

    function poll() {
        fetch('<?= e(url('/admin/package/status')) ?>', {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (renderTask(data.task)) { scheduleNext(1500); }
            })
            .catch(function () { scheduleNext(4000); });
    }

    // 页面有进行中 / 未消费任务时自动开始轮询（CLI 创建的任务也能在此跟进）
    <?php if ($task !== null): ?>
    var current = <?= json_encode($task, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    if (renderTask(current)) { scheduleNext(200); }
    <?php else: ?>
    scheduleNext(200);
    <?php endif; ?>
})();
</script>
<?php endif; ?>
