<?php use App\Core\View;

/**
 * 在线使用降级页：多文件工具无法在单一路由下提供相对资源
 *
 * @var array<string, mixed> $tool
 */
$toolId = (string) $tool['tool_id'];
?><nav class="breadcrumb" aria-label="面包屑">
  <a href="<?= e(url('/')) ?>">首页</a>
  <span class="breadcrumb-sep">/</span>
  <a href="<?= e(url('/tool/' . rawurlencode($toolId))) ?>"><?= e((string) $tool['title']) ?></a>
  <span class="breadcrumb-sep">/</span>
  <span>在线使用</span>
</nav>

<div class="card use-fallback-card">
  <div class="card-body">
    <h1 class="use-fallback-title"><?= icon('package') ?>该工具包含多个文件</h1>
    <p class="use-fallback-text">
      「<?= e((string) $tool['title']) ?>」由多个文件组成，不支持浏览器内直接打开。
      请通过合集包获取完整文件，解压后双击主文件即可使用。
    </p>
    <div class="use-fallback-actions">
      <a class="btn btn-primary btn-lg" href="<?= e(url('/tool/' . rawurlencode($toolId))) ?>">
        <?= icon('package') ?>返回详情页获取
      </a>
    </div>
  </div>
</div>
