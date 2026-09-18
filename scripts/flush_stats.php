<?php
declare(strict_types=1);

/**
 * 统计缓冲批量落库（CLI，供 cron 每分钟调度）
 *
 * 用法：
 *   php scripts/flush_stats.php            把 var/cache/stats-buffer.jsonl 落入 storage/stats.db
 *   php scripts/flush_stats.php --check    只报告缓冲状态，不落库
 *
 * 退出码：0 成功（含无可落数据）/ 1 失败
 *
 * 部署：crontab 加  * * * * * php /path/to/KeeTools/scripts/flush_stats.php
 * 未配置 cron 时系统也能工作：缓冲超过 512KB 会请求内惰性落库，
 * 且后台统计看板每次渲染前会先 flush。
 */

use App\Core\App;
use App\Core\Autoloader;
use App\Core\Database;
use App\Core\Env;
use App\Services\StatsService;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "[ERR] 仅限命令行执行\n");
    exit(1);
}

$basePath = dirname(__DIR__);

require $basePath . '/src/core/Autoloader.php';
Autoloader::register('App\\', $basePath . '/src');

App::setBasePath($basePath);
Env::load($basePath . '/.env');

// 连接业务库与统计库（StatsService 落库需要 stats 库连接）
App::setDb(new Database(App::path(Env::get('DB_PATH', 'storage/app.db') ?: 'storage/app.db')));
App::setStatsDb(new Database(App::path(Env::get('STATS_DB_PATH', 'storage/stats.db') ?: 'storage/stats.db'), true));

$buffer = App::path('var/cache/stats-buffer.jsonl');
$pending = is_file($buffer) ? (int) (filesize($buffer) ?: 0) : 0;

if (in_array('--check', $argv, true)) {
    echo "[INFO] 缓冲 " . number_format($pending) . " 字节";
    echo $pending > 0 ? "（待落库）\n" : "（空）\n";
    exit(0);
}

if ($pending === 0) {
    echo "[OK]  缓冲为空，无需落库\n";
    exit(0);
}

try {
    $count = StatsService::flush();
    echo "[OK]  已落库 {$count} 条统计记录\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, '[ERR] 落库失败: ' . $e->getMessage() . "\n");
    exit(1);
}
