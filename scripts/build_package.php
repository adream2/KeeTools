<?php
declare(strict_types=1);

/**
 * 离线包打包任务消费端（CLI，P3）
 *
 * 用法：
 *   php scripts/build_package.php              处理队列中最早的一条 pending 任务
 *   php scripts/build_package.php --task=12    执行指定任务
 *   php scripts/build_package.php --list       列出任务与产物概览
 *
 * 退出码：0 成功（含队列为空）/ 1 失败
 *
 * 后台未配置 cron 也能工作：后台打包页轮询 /admin/package/status 时会惰性
 * 消费 pending 任务；本脚本供 crontab 定时调度或手动触发。
 * 部署：* * * * * php /path/to/KeeTools/scripts/build_package.php
 *
 * 引导方式与 init_db.php / flush_stats.php 一致：不走完整 bootstrap
 * （避免启动会话与路由），手动注册自动加载并连接双库。
 */

use App\Core\App;
use App\Core\Autoloader;
use App\Core\Database;
use App\Core\Env;
use App\Services\PackageBuilder;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "[ERR] 仅限命令行执行\n");
    exit(1);
}

$basePath = dirname(__DIR__);

require $basePath . '/src/core/Autoloader.php';
Autoloader::register('App\\', $basePath . '/src');

App::setBasePath($basePath);
Env::load($basePath . '/.env');

// PortalRenderer / PackageBuilder 的纯文本产物依赖 helpers（site_name 等），
// Config 三级读取依赖业务库
require_once $basePath . '/src/core/helpers.php';
App::setDb(new Database(App::path(Env::get('DB_PATH', 'storage/app.db') ?: 'storage/app.db')));

$builder = new PackageBuilder();

// ── --list：概览模式 ─────────────────────────────────────
if (in_array('--list', $argv, true)) {
    $tasks = App::db()->fetchAll(
        'SELECT id, slug, version, status, progress, message, created_at
         FROM package_tasks ORDER BY id DESC LIMIT 10'
    );
    echo "[INFO] 最近任务：\n";
    foreach ($tasks as $t) {
        printf("  #%-4d %s v%s  %-8s %3d%%  %s  %s\n", (int) $t['id'], $t['slug'], $t['version'], $t['status'], (int) $t['progress'], (string) $t['created_at'], (string) $t['message']);
    }
    $packages = App::db()->fetchAll(
        'SELECT file_name, tool_count, size_bytes, created_at FROM packages ORDER BY id DESC LIMIT 10'
    );
    echo "[INFO] 产物：\n";
    foreach ($packages as $p) {
        printf("  %s  %d 工具  %.2fMB  %s\n", (string) $p['file_name'], (int) $p['tool_count'], (int) $p['size_bytes'] / 1048576, (string) $p['created_at']);
    }
    if ($tasks === [] && $packages === []) {
        echo "  （暂无记录）\n";
    }
    exit(0);
}

// ── --task=N：执行指定任务 ────────────────────────────────
$taskId = null;
foreach ($argv as $arg) {
    if (preg_match('/^--task=(\d+)$/', $arg, $m) === 1) {
        $taskId = (int) $m[1];
    }
}

@set_time_limit(0);

try {
    if ($taskId !== null) {
        $builder->run($taskId);
    } else {
        $taskId = $builder->processPending();
        if ($taskId === null) {
            echo "[OK]  队列为空，无待处理任务\n";
            exit(0);
        }
    }

    $task = $builder->findTask($taskId);
    if ($task !== null && $task['status'] === 'done') {
        echo "[OK]  任务 #{$taskId}：" . (string) $task['message'] . "\n";
        exit(0);
    }

    // processPending 吞掉的失败在这里显式报告
    if ($task !== null && $task['status'] === 'failed') {
        fwrite(STDERR, "[ERR] 任务 #{$taskId} 失败：" . (string) $task['message'] . "\n");
        exit(1);
    }

    echo "[OK]  任务 #{$taskId} 已处理\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, '[ERR] ' . $e->getMessage() . "\n");
    exit(1);
}
