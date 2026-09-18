<?php
declare(strict_types=1);

/**
 * 数据库初始化 —— 建库建表 + 分类种子数据
 *
 * 作用 / 用法 / 退出码：
 *   php scripts/init_db.php            幂等初始化（已存在则跳过已建表）
 *   php scripts/init_db.php --force    删除现有库文件后重建（危险，会清空数据）
 *   php scripts/init_db.php --check    校验库与表是否就绪，不修改任何数据
 *
 * 产物：storage/app.db（业务）+ storage/stats.db（统计，物理隔离）
 * 退出码：0 成功 / 1 失败
 *
 * 设计依据：docs/需求文档-v2.md §9 数据库设计、docs/架构说明.md §4.4。
 * 分类种子 = 学段一级 + 学科二级，slug 形如 junior-physics（见 manifest 规范 §3.1/§3.2）。
 *
 * 本脚本只依赖 App\Core\{Autoloader, Env, App, Database}，不走完整
 * bootstrap.php（避免 CLI 下启动会话与路由）。
 */

use App\Core\App;
use App\Core\Autoloader;
use App\Core\Database;
use App\Core\Env;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "[ERR] 仅限命令行执行\n");
    exit(1);
}

// ── 引导：自动加载 + .env + 基准路径 ─────────────────────
$basePath = dirname(__DIR__);

require $basePath . '/src/core/Autoloader.php';
// 第二参数是 App\ 命名空间的根目录（与 bootstrap.php 一致），App\Core\App → src/core/App.php
Autoloader::register('App\\', $basePath . '/src');

App::setBasePath($basePath);
Env::load($basePath . '/.env');

// ── 参数解析 ─────────────────────────────────────────────
$force = in_array('--force', $argv, true);
$check = in_array('--check', $argv, true);

/** 需要存在于业务库中的全部表（--check 用） */
const TABLES = [
    'categories', 'tools', 'tool_category', 'tool_netdisks',
    'admins', 'site_config', 'login_attempts', 'tool_overrides',
];

$dbPath = App::path(Env::get('DB_PATH', 'storage/app.db') ?: 'storage/app.db');
$statsDbPath = App::path(Env::get('STATS_DB_PATH', 'storage/stats.db') ?: 'storage/stats.db');

// ── --check 模式：只读校验 ───────────────────────────────
if ($check) {
    $missing = [];
    foreach ([$dbPath, $statsDbPath] as $file) {
        if (!is_file($file)) {
            $missing[] = basename($file) . ' 不存在（先运行 php scripts/init_db.php）';
        }
    }
    if ($missing === []) {
        $db = new Database($dbPath);
        foreach (TABLES as $table) {
            if (!$db->tableExists($table)) {
                $missing[] = "app.db 缺表 {$table}";
            }
        }
        $stats = new Database($statsDbPath, true);
        foreach (['stats'] as $table) {
            if (!$stats->tableExists($table)) {
                $missing[] = "stats.db 缺表 {$table}";
            }
        }
    }
    if ($missing !== []) {
        foreach ($missing as $m) {
            fwrite(STDERR, "[FAIL] {$m}\n");
        }
        exit(1);
    }
    echo "[OK] 数据库与全部数据表就绪\n";
    exit(0);
}

// ── --force：删除现有库重建 ──────────────────────────────
if ($force) {
    foreach ([$dbPath, $statsDbPath] as $file) {
        foreach (['', '-wal', '-shm'] as $suffix) {
            $f = $file . $suffix;
            if (is_file($f)) {
                @unlink($f);
            }
        }
    }
    echo "[WARN] 已删除现有库文件，开始重建\n";
}

// ── 表结构（业务库 app.db）───────────────────────────────
// SQL 保持标准写法（需求文档 §9.2），不使用 INSERT OR REPLACE 等
// SQLite 专有语法；主键用 INTEGER PRIMARY KEY（rowid 别名），
// 迁移 MySQL 时仅需一次性脚本处理时间函数与 JSON 字段。
const APP_SCHEMA = <<<'SQL'
CREATE TABLE IF NOT EXISTS categories (
    id         INTEGER PRIMARY KEY,
    name       TEXT    NOT NULL,
    slug       TEXT    NOT NULL UNIQUE,
    parent_id  INTEGER REFERENCES categories(id) ON DELETE CASCADE,
    level      INTEGER NOT NULL DEFAULT 1 CHECK (level IN (1, 2)),
    sort_order INTEGER NOT NULL DEFAULT 0,
    icon       TEXT    NULL
);

CREATE INDEX IF NOT EXISTS idx_categories_parent ON categories(parent_id);
CREATE INDEX IF NOT EXISTS idx_categories_level  ON categories(level);

-- 工具索引表：manifest 镜像字段 + 用户态字段（featured/sort/publish）
-- JSON 列以 TEXT 存储，读取方负责 json_decode（迁移 MySQL 时换 JSON 型）
CREATE TABLE IF NOT EXISTS tools (
    id            INTEGER PRIMARY KEY,
    tool_id       TEXT    NOT NULL UNIQUE,          -- = manifest.id = 目录名
    title         TEXT    NOT NULL,
    description   TEXT    NOT NULL DEFAULT '',
    version       TEXT    NOT NULL DEFAULT '0.0.0', -- 语义化版本 x.y.z
    type          TEXT    NOT NULL DEFAULT 'shell' CHECK (type IN ('fixed', 'shell', 'experiment')),
    family        TEXT    NULL,
    grade_range   TEXT    NOT NULL DEFAULT '[]',    -- JSON 数组，如 ["1-6","7-9"]
    subjects      TEXT    NOT NULL DEFAULT '[]',    -- JSON 数组，如 ["物理","化学"]
    tags          TEXT    NOT NULL DEFAULT '[]',    -- JSON 数组
    author        TEXT    NOT NULL DEFAULT '',
    entry         TEXT    NOT NULL DEFAULT 'index.html',
    single_file   INTEGER NOT NULL DEFAULT 1,
    offline       INTEGER NOT NULL DEFAULT 1,
    screen        TEXT    NOT NULL DEFAULT 'large',
    stats_enabled INTEGER NOT NULL DEFAULT 1,
    license       TEXT    NOT NULL DEFAULT 'free',
    is_featured   INTEGER NOT NULL DEFAULT 0,       -- 用户态：推荐
    is_published  INTEGER NOT NULL DEFAULT 1,       -- 用户态：上架
    sort_order    INTEGER NOT NULL DEFAULT 0,       -- 用户态：排序
    dir_path      TEXT    NOT NULL DEFAULT '',      -- 相对 tools/ 的目录名（冗余存储，加速路径校验）
    manifest_mtime INTEGER NOT NULL DEFAULT 0,      -- manifest 修改时间，增量扫描用
    meta_mismatch INTEGER NOT NULL DEFAULT 0,      -- ET-META 与 manifest 版本不一致标记
    created_at    TEXT    NOT NULL,
    updated_at    TEXT    NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_tools_type     ON tools(type);
CREATE INDEX IF NOT EXISTS idx_tools_family   ON tools(family);
CREATE INDEX IF NOT EXISTS idx_tools_publish  ON tools(is_published, is_featured, sort_order);

CREATE TABLE IF NOT EXISTS tool_category (
    tool_id     TEXT    NOT NULL REFERENCES tools(tool_id) ON DELETE CASCADE,
    category_id INTEGER NOT NULL REFERENCES categories(id) ON DELETE CASCADE,
    PRIMARY KEY (tool_id, category_id)
);

CREATE INDEX IF NOT EXISTS idx_tool_category_cat ON tool_category(category_id);

CREATE TABLE IF NOT EXISTS tool_netdisks (
    id              INTEGER PRIMARY KEY,
    tool_id         TEXT    NOT NULL REFERENCES tools(tool_id) ON DELETE CASCADE,
    netdisk_type    TEXT    NOT NULL,                 -- 123 / quark / baidu 等
    url             TEXT    NOT NULL,
    extract_code    TEXT    NULL,                     -- 提取码（展示在中间页，不放包内）
    package_name    TEXT    NULL,
    file_size       INTEGER NULL,
    is_active       INTEGER NOT NULL DEFAULT 1,
    last_checked_at TEXT    NULL,                     -- 探活时间
    check_status    TEXT    NULL                      -- ok / invalid / null=未探活
);

CREATE INDEX IF NOT EXISTS idx_netdisks_tool ON tool_netdisks(tool_id, is_active);

CREATE TABLE IF NOT EXISTS admins (
    id            INTEGER PRIMARY KEY,
    username      TEXT NOT NULL UNIQUE,
    password_hash TEXT NULL,                          -- 为 NULL = 未设密码（仅无密码后台/IP 白名单可用）
    role          TEXT NOT NULL DEFAULT 'admin' CHECK (role IN ('admin', 'editor')),
    last_login    TEXT NULL,
    created_at    TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS site_config (
    key   TEXT PRIMARY KEY,
    value TEXT NOT NULL DEFAULT ''
);

-- 登录限流：按用户名与 IP 两个维度计数
CREATE TABLE IF NOT EXISTS login_attempts (
    id           INTEGER PRIMARY KEY,
    username     TEXT NOT NULL DEFAULT '',
    ip_hash      TEXT NOT NULL,                       -- Security::hashIp() 输出，不存明文 IP
    attempted_at TEXT NOT NULL,
    succeeded    INTEGER NOT NULL DEFAULT 0
);

CREATE INDEX IF NOT EXISTS idx_attempts_user ON login_attempts(username, attempted_at);
CREATE INDEX IF NOT EXISTS idx_attempts_ip   ON login_attempts(ip_hash, attempted_at);

-- tools/ 不可写时的 manifest 回写降级方案：按工具存 JSON 覆盖
CREATE TABLE IF NOT EXISTS tool_overrides (
    tool_id    TEXT PRIMARY KEY REFERENCES tools(tool_id) ON DELETE CASCADE,
    overrides  TEXT NOT NULL DEFAULT '{}',            -- JSON 对象，键为 manifest 字段
    updated_at TEXT NOT NULL
);
SQL;

// ── 表结构（统计库 stats.db，物理隔离写锁）───────────────
const STATS_SCHEMA = <<<'SQL'
-- 只保三个事件：view / use_online / netdisk_click（需求文档 §10.1）
CREATE TABLE IF NOT EXISTS stats (
    id           INTEGER PRIMARY KEY,
    tool_id      TEXT NOT NULL,
    event_type   TEXT NOT NULL CHECK (event_type IN ('view', 'use_online', 'netdisk_click')),
    netdisk_type TEXT NULL,                           -- 仅 netdisk_click 用
    ip_hash      TEXT NULL,
    ua_hash      TEXT NULL,
    referer      TEXT NULL,
    created_at   TEXT NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_stats_tool  ON stats(tool_id, event_type, created_at);
CREATE INDEX IF NOT EXISTS idx_stats_time  ON stats(created_at);
SQL;

// ── 建表 ────────────────────────────────────────────────
try {
    $db = new Database($dbPath);
    $db->runSqlString(APP_SCHEMA);
    $tableCount = count(array_filter(TABLES, [$db, 'tableExists']));
    echo "[OK]  app.db 建表完成（{$tableCount}/" . count(TABLES) . " 张表）\n";

    $stats = new Database($statsDbPath, true);
    $stats->runSqlString(STATS_SCHEMA);
    echo "[OK]  stats.db 建表完成\n";
} catch (Throwable $e) {
    fwrite(STDERR, '[ERR] 建表失败: ' . $e->getMessage() . "\n");
    exit(1);
}

// ── 分类种子数据（幂等：表空才写）────────────────────────
// 学段一级 + 学科二级，slug 组合形如 junior-physics
$seed = [
    // slug => [名称, 图标, [学科 slug => 名称]]
    'primary' => ['小学', 'school', [
        'chinese' => '语文', 'math' => '数学', 'english' => '英语',
        'science' => '科学', 'it' => '信息技术', 'pe' => '体育',
        'music' => '音乐', 'art' => '美术',
    ]],
    'junior' => ['初中', 'graduation-cap', [
        'chinese' => '语文', 'math' => '数学', 'english' => '英语',
        'physics' => '物理', 'chemistry' => '化学', 'biology' => '生物',
        'politics' => '政治', 'history' => '历史', 'geography' => '地理',
        'it' => '信息技术', 'pe' => '体育', 'music' => '音乐', 'art' => '美术',
    ]],
    'senior' => ['高中', 'book-open', [
        'chinese' => '语文', 'math' => '数学', 'english' => '英语',
        'physics' => '物理', 'chemistry' => '化学', 'biology' => '生物',
        'politics' => '政治', 'history' => '历史', 'geography' => '地理',
        'it' => '信息技术', 'pe' => '体育', 'music' => '音乐', 'art' => '美术',
    ]],
];

try {
    $existing = (int) $db->fetchColumn('SELECT COUNT(*) FROM categories');
    if ($existing > 0) {
        echo "[OK]  分类已有 {$existing} 条，跳过种子数据\n";
    } else {
        $now = date('Y-m-d H:i:s');
        $rows = [];
        $order = 0;
        foreach ($seed as $gradeSlug => [$gradeName, $icon, $subjects]) {
            $rows[] = [':name' => $gradeName, ':slug' => $gradeSlug, ':parent' => null,
                ':level' => 1, ':sort' => $order++, ':icon' => $icon];
        }
        // 二级分类需要在拿到父级 id 后插入，故分两轮
        $db->batchInsert(
            'INSERT INTO categories (name, slug, parent_id, level, sort_order, icon)
             VALUES (:name, :slug, :parent, :level, :sort, :icon)',
            $rows
        );

        $childRows = [];
        foreach ($seed as $gradeSlug => [$gradeName, , $subjects]) {
            $parentId = $db->fetchColumn(
                'SELECT id FROM categories WHERE slug = :slug AND level = 1',
                [':slug' => $gradeSlug]
            );
            if ($parentId === null) {
                throw new RuntimeException("一级分类 {$gradeSlug} 插入失败");
            }
            $subOrder = 0;
            foreach ($subjects as $subSlug => $subName) {
                $childRows[] = [':name' => $subName, ':slug' => "{$gradeSlug}-{$subSlug}",
                    ':parent' => (int) $parentId, ':level' => 2, ':sort' => $subOrder++, ':icon' => null];
            }
        }
        $db->batchInsert(
            'INSERT INTO categories (name, slug, parent_id, level, sort_order, icon)
             VALUES (:name, :slug, :parent, :level, :sort, :icon)',
            $childRows
        );

        $total = (int) $db->fetchColumn('SELECT COUNT(*) FROM categories');
        echo "[OK]  分类种子写入完成（3 学段 + " . ($total - 3) . " 学科，共 {$total} 条）\n";
    }
} catch (Throwable $e) {
    fwrite(STDERR, '[ERR] 种子数据写入失败: ' . $e->getMessage() . "\n");
    exit(1);
}

echo "[OK]  数据库初始化完成\n";
exit(0);
