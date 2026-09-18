<?php

/**
 * 广告位渲染（零痕迹约定：不可渲染时不输出任何节点）
 *
 * @var array{slot: string, code: string, device: string}|null $slot SiteOps::adSlot() 结果
 * @var string $position 页面内位置修饰（可选，如 top / bottom）
 */
if ($slot === null) {
    return;
}
$position = $position ?? '';
?><div class="ad-slot ad-slot--<?= e($slot['slot']) ?><?= $position !== '' ? ' ad-slot--' . e($position) : '' ?><?= $slot['device'] !== 'all' ? ' ad-slot--device-' . e($slot['device']) : '' ?>">
  <?= $slot['code'] /* 原始 HTML：仅 admin 可写，原样输出（设计 §2） */ ?>
  <span class="ad-label">广告</span>
</div>
