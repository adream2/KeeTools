<?php

/**
 * 设置页 Tab 导航（分区保存框架）
 *
 * @var string $activeTab '' | footer | links | ads | announce | sponsor
 */
$tabs = [
    ''         => ['基本', 'settings'],
    'footer'   => ['导航与页脚', 'layout-list'],
    'links'    => ['友链', 'link'],
    'ads'      => ['广告位', 'image'],
    'announce' => ['公告', 'megaphone'],
    'sponsor'  => ['赞助', 'heart-handshake'],
];
$layoutListIcon = 'list';
?><nav class="settings-tabs" aria-label="设置分区">
  <?php foreach ($tabs as $key => [$label, $tabIcon]): ?>
    <a class="settings-tab<?= $activeTab === $key ? ' is-active' : '' ?>"
       href="<?= e(url('/admin/settings' . ($key === '' ? '' : '/' . $key))) ?>">
      <?= icon($tabIcon === 'layout-list' ? $layoutListIcon : $tabIcon) ?><?= e($label) ?>
    </a>
  <?php endforeach; ?>
</nav>
