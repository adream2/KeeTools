<?php

/**
 * 失效反馈成功页
 *
 * @var array<string, mixed> $tool
 * @var string $typeLabel
 */
$toolId = (string) $tool['tool_id'];
?><nav class="breadcrumb" aria-label="面包屑">
  <a href="<?= e(url('/')) ?>">首页</a>
  <span class="breadcrumb-sep">/</span>
  <a href="<?= e(url('/tool/' . rawurlencode($toolId))) ?>"><?= e((string) $tool['title']) ?></a>
  <span class="breadcrumb-sep">/</span>
  <span>失效反馈</span>
</nav>

<div class="card netdisk-wrap">
  <div class="card-body" style="text-align:center; padding: var(--sp-8) var(--sp-4);">
    <h1 class="netdisk-title"><?= icon('check') ?>反馈已收到</h1>
    <p class="netdisk-sub">感谢！「<?= e($typeLabel) ?>」链接已标记待处理，站长会尽快更新。</p>
    <div class="use-fallback-actions">
      <a class="btn btn-primary" href="<?= e(url('/netdisk/' . rawurlencode($toolId))) ?>">
        <?= icon('cloud') ?>返回换一个网盘
      </a>
      <a class="btn" href="<?= e(url('/tool/' . rawurlencode($toolId))) ?>"><?= icon('arrow-left') ?>返回工具页</a>
    </div>
  </div>
</div>
