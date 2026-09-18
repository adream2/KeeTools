<?php
/**
 * 工具卡片（首页 / 分类页 / 搜索页通用）
 *
 * @var array<string, mixed> $tool 已由 ToolRepository 解码的工具行
 */
$type = isset($tool['type']) ? (string) $tool['type'] : null;
$tags = is_array($tool['tags'] ?? null) ? $tool['tags'] : [];
?><a class="card card-tool" href="<?= e(url('/tool/' . rawurlencode((string) $tool['tool_id']))) ?>">
  <div class="card-body">
    <div class="card-tool-top">
      <span class="card-tool-icon"><?= icon(tool_type_icon($type)) ?></span>
      <span class="tag <?= e(tool_type_tag_class($type)) ?>"><?= e(tool_type_label($type)) ?></span>
    </div>
    <h3 class="card-tool-title"><?= e((string) $tool['title']) ?></h3>
    <p class="card-tool-desc"><?= e((string) $tool['description']) ?></p>
    <div class="card-tool-meta">
      <span class="tag-group">
        <?php foreach (array_slice($tags, 0, 2) as $tag): ?>
          <span class="tag"><?= e((string) $tag) ?></span>
        <?php endforeach; ?>
      </span>
      <span class="card-tool-version">v<?= e((string) $tool['version']) ?></span>
    </div>
  </div>
</a>
