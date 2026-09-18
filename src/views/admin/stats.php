<?php
/**
 * 统计看板
 *
 * @var bool $ready
 * @var array<string, int> $totals
 * @var list<array{type: string, n: int}> $netdiskRanking
 * @var array<string, list<array{tool_id: string, n: int}>> $toolRanking
 * @var list<array{date: string, view: int, use_online: int, netdisk_click: int}> $trend
 * @var float|null $conversion
 * @var int $reported
 */
?><div class="page-head">
  <h1 class="page-title">统计看板</h1>
  <p class="page-desc">只统计 view / use_online / netdisk_click / download_direct / sponsor_click 五类事件</p>
</div>

<?php if (!$ready): ?>
  <div class="alert alert-warning">
    <?= icon('alert-triangle') ?>
    <div>统计库未初始化：请先执行 <code>php scripts/init_db.php</code>。</div>
  </div>
<?php else: ?>
  <?php if ($reported > 0): ?>
    <div class="alert alert-warning">
      <?= icon('flag') ?>
      <div>有 <strong><?= e((string) $reported) ?></strong> 条用户反馈「链接失效」待处理 ——
        <a href="<?= e(url('/admin/netdisks')) ?>">前往网盘管理</a></div>
    </div>
  <?php endif; ?>

  <div class="stat-cards">
    <div class="card stat-card">
      <div class="card-body">
        <div class="stat-label"><?= icon('eye') ?>详情页浏览</div>
        <div class="stat-value"><?= e((string) $totals['view']) ?></div>
      </div>
    </div>
    <div class="card stat-card">
      <div class="card-body">
        <div class="stat-label"><?= icon('play') ?>在线使用</div>
        <div class="stat-value"><?= e((string) $totals['use_online']) ?></div>
      </div>
    </div>
    <div class="card stat-card">
      <div class="card-body">
        <div class="stat-label"><?= icon('cloud-download') ?>网盘点击</div>
        <div class="stat-value"><?= e((string) $totals['netdisk_click']) ?></div>
      </div>
    </div>
    <div class="card stat-card">
      <div class="card-body">
        <div class="stat-label"><?= icon('download') ?>单文件下载</div>
        <div class="stat-value"><?= e((string) $totals['download_direct']) ?></div>
      </div>
    </div>
    <div class="card stat-card">
      <div class="card-body">
        <div class="stat-label"><?= icon('heart') ?>赞助支持</div>
        <div class="stat-value"><?= e((string) $totals['sponsor_click']) ?></div>
      </div>
    </div>
    <div class="card stat-card">
      <div class="card-body">
        <div class="stat-label"><?= icon('trending-up') ?>浏览 → 网盘转化率</div>
        <div class="stat-value"><?= $conversion !== null ? e((string) $conversion) . '%' : '—' ?></div>
      </div>
    </div>
  </div>

  <div class="card" style="margin-top: var(--sp-5);">
    <div class="card-head">
      <h2 class="card-title">近 30 天趋势</h2>
    </div>
    <div class="card-body">
      <div class="trend-chart" role="img" aria-label="近30天每日事件趋势">
        <?php $max = 0; ?>
        <?php foreach ($trend as $row): ?>
          <?php $max = max($max, $row['view'] + $row['use_online'] + $row['netdisk_click']); ?>
        <?php endforeach; ?>
        <?php foreach ($trend as $row): ?>
          <?php $sum = $row['view'] + $row['use_online'] + $row['netdisk_click']; ?>
          <div class="trend-col" title="<?= e($row['date']) ?>：浏览 <?= e((string) $row['view']) ?> · 使用 <?= e((string) $row['use_online']) ?> · 网盘 <?= e((string) $row['netdisk_click']) ?>">
            <div class="trend-bar" style="height: <?= $max > 0 ? round($sum / $max * 100) : 0 ?>%;"></div>
          </div>
        <?php endforeach; ?>
      </div>
      <div class="table-sub" style="margin-top: var(--sp-2);">柱高 = 当日三类事件总数；悬停查看明细。最近日期在右侧。</div>
    </div>
  </div>

  <div class="card" style="margin-top: var(--sp-5);">
    <div class="card-head">
      <h2 class="card-title">网盘点击排行（变现核心）</h2>
    </div>
    <div class="card-body">
      <?php if ($netdiskRanking === []): ?>
        <div class="empty"><div class="empty-title">暂无网盘点击数据</div></div>
      <?php else: ?>
        <div class="table-wrap">
          <table class="table">
            <thead><tr><th>网盘</th><th>点击量</th><th>占比</th></tr></thead>
            <tbody>
              <?php $sumType = array_sum(array_column($netdiskRanking, 'n')); ?>
              <?php foreach ($netdiskRanking as $row): ?>
                <tr>
                  <td><?= e(\App\Services\NetdiskRepository::TYPES[$row['type']] ?? $row['type']) ?></td>
                  <td class="tool-meta-mono"><?= e((string) $row['n']) ?></td>
                  <td class="tool-meta-mono"><?= $sumType > 0 ? e((string) round($row['n'] / $sumType * 100, 1)) . '%' : '—' ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="card" style="margin-top: var(--sp-5);">
    <div class="card-head">
      <h2 class="card-title">工具排行 Top 10</h2>
    </div>
    <div class="card-body">
      <div class="table-wrap">
        <table class="table">
          <thead><tr><th>工具</th><th>浏览</th><th>在线使用</th><th>网盘点击</th></tr></thead>
          <tbody>
            <?php
            $rows = [];
            $titles = [];
            foreach (array_keys($toolRanking) as $event) {
                foreach ($toolRanking[$event] as $i => $r) {
                    $tid = $r['tool_id'];
                    $titles[$tid] = $r['title'];
                    $rows[$tid] = $rows[$tid] ?? ['view' => 0, 'use_online' => 0, 'netdisk_click' => 0];
                    $rows[$tid][$event] = $r['n'];
                }
            }
            // uasort 保持 tool_id 字符串键（usort 会重排为整数索引，丢掉键名）
            uasort($rows, static fn (array $a, array $b): int => $b['netdisk_click'] <=> $a['netdisk_click'] ?: $b['view'] <=> $a['view']);
            ?>
            <?php if ($rows === []): ?>
              <tr><td colspan="4" class="table-sub">暂无数据</td></tr>
            <?php else: ?>
              <?php foreach ($rows as $tid => $r): ?>
                <tr>
                  <td>
                    <strong><?= e($titles[$tid] ?? $tid) ?></strong>
                    <div class="table-sub"><a href="<?= e(url('/tool/' . rawurlencode($tid))) ?>" target="_blank" rel="noopener"><?= e($tid) ?></a></div>
                  </td>
                  <td class="tool-meta-mono"><?= e((string) $r['view']) ?></td>
                  <td class="tool-meta-mono"><?= e((string) $r['use_online']) ?></td>
                  <td class="tool-meta-mono"><?= e((string) $r['netdisk_click']) ?></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
<?php endif; ?>
