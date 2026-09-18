<?php
declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use PDOStatement;
use RuntimeException;

/**
 * PDO 封装 —— 全站唯一数据库出口
 *
 * 业务库与统计库物理分离：统计写入是全站最高频操作，而 SQLite 的写锁
 * 是库级全局锁，若与业务写入同库，后台保存工具配置时会大量出现
 * SQLITE_BUSY。分离后两者互不阻塞。
 *
 * 换库预案：所有 SQL 均参数化且保持标准写法，迁移 MySQL 时只需改本类
 * 的连接串与少量 PRAGMA，不引入 ORM。
 */
final class Database
{
    private PDO $pdo;

    /** 当前是否为统计库（决定是否跳过部分 PRAGMA） */
    private bool $isStats;

    public function __construct(string $dbPath, bool $isStats = false)
    {
        $this->isStats = $isStats;
        $this->pdo = self::connect($dbPath);
    }

    /**
     * 建立连接并施加 PRAGMA。
     *
     * @throws RuntimeException 连接失败时抛出
     */
    public static function connect(string $dbPath): PDO
    {
        $dir = dirname($dbPath);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('数据目录无法创建: ' . $dir);
        }

        try {
            $pdo = new PDO('sqlite:' . $dbPath, null, null, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                // 用真实预处理而非模拟，杜绝驱动层拼接带来的注入面
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_STRINGIFY_FETCHES  => false,
            ]);
        } catch (PDOException $e) {
            throw new RuntimeException('数据库连接失败: ' . $e->getMessage(), 0, $e);
        }

        // WAL 允许读写并发，是缓解 SQLITE_BUSY 的核心手段
        $pdo->exec('PRAGMA journal_mode = WAL');
        // 拿到锁后最多等 5 秒再报错，而非立即失败
        $pdo->exec('PRAGMA busy_timeout = 5000');
        // WAL 下 NORMAL 已足够安全，兼顾写入性能
        $pdo->exec('PRAGMA synchronous = NORMAL');
        $pdo->exec('PRAGMA foreign_keys = ON');

        return $pdo;
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    public function isStats(): bool
    {
        return $this->isStats;
    }

    /**
     * 执行写语句，返回受影响行数。
     *
     * @param array<string|int, mixed> $params
     */
    public function execute(string $sql, array $params = []): int
    {
        $stmt = $this->run($sql, $params);

        return $stmt->rowCount();
    }

    /**
     * 查询单行。
     *
     * @param array<string|int, mixed> $params
     * @return array<string, mixed>|null
     */
    public function fetch(string $sql, array $params = []): ?array
    {
        $row = $this->run($sql, $params)->fetch();

        return $row === false ? null : $row;
    }

    /**
     * 查询多行。
     *
     * @param array<string|int, mixed> $params
     * @return array<int, array<string, mixed>>
     */
    public function fetchAll(string $sql, array $params = []): array
    {
        return $this->run($sql, $params)->fetchAll();
    }

    /**
     * 查询单列标量值。
     *
     * @param array<string|int, mixed> $params
     */
    public function fetchColumn(string $sql, array $params = [], int $column = 0): mixed
    {
        $value = $this->run($sql, $params)->fetchColumn($column);

        return $value === false ? null : $value;
    }

    /**
     * 插入并返回自增主键。
     *
     * @param array<string|int, mixed> $params
     */
    public function insert(string $sql, array $params = []): int
    {
        $this->run($sql, $params);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * 在事务中执行回调。异常时自动回滚并向上抛出。
     *
     * 不用 PDO::beginTransaction 的嵌套计数，因为本项目无需嵌套事务；
     * 显式 BEGIN/COMMIT 更易排查。
     *
     * @template T
     * @param callable(self): T $callback
     * @return T
     */
    public function transaction(callable $callback): mixed
    {
        $this->pdo->exec('BEGIN');
        try {
            $result = $callback($this);
            $this->pdo->exec('COMMIT');

            return $result;
        } catch (\Throwable $e) {
            $this->pdo->exec('ROLLBACK');
            throw $e;
        }
    }

    /**
     * 批量插入（单事务，避免 N 次提交的开销）。
     *
     * @param array<int, array<string|int, mixed>> $rowsList
     */
    public function batchInsert(string $sql, array $rowsList): int
    {
        if ($rowsList === []) {
            return 0;
        }

        return $this->transaction(function (self $db) use ($sql, $rowsList): int {
            $stmt = $db->pdo()->prepare($sql);
            $count = 0;
            foreach ($rowsList as $params) {
                $stmt->execute($params);
                $count += $stmt->rowCount();
            }

            return $count;
        });
    }

    /**
     * 表是否存在（迁移与首次运行判定用）。
     */
    public function tableExists(string $table): bool
    {
        $row = $this->fetch(
            "SELECT name FROM sqlite_master WHERE type = 'table' AND name = :name",
            [':name' => $table]
        );

        return $row !== null;
    }

    /**
     * 执行多语句 SQL 字符串（建表脚本）。
     *
     * 建表脚本不含字符串内的分号或触发器体，整段 exec 即可，
     * SQLite PDO 驱动支持一次执行多条语句。
     */
    public function runSqlString(string $sql): void
    {
        $this->pdo->exec($sql);
    }

    /**
     * 执行 SQL 文件（建表脚本）。
     */
    public function runSqlFile(string $file): void
    {
        $sql = @file_get_contents($file);
        if ($sql === false) {
            throw new RuntimeException('SQL 文件读取失败: ' . $file);
        }

        $this->runSqlString($sql);
    }

    /**
     * @param array<string|int, mixed> $params
     */
    private function run(string $sql, array $params): PDOStatement
    {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

            return $stmt;
        } catch (PDOException $e) {
            // 记录 SQL 但**不记录参数值**，参数可能含密码哈希等敏感数据
            Logger::error('SQL 执行失败', [
                'sql'     => $sql,
                'message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
