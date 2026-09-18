<?php
declare(strict_types=1);

namespace App\Core;

/**
 * 配置读取
 *
 * 优先级：.env > 数据库 site_config 表 > 代码默认值。
 *
 * 为什么是这个顺序：.env 代表部署环境事实（路径、密钥、调试开关），
 * 必须最高优先；site_config 是运营可在后台改的内容（站点名、描述、
 * 功能开关），放在 .env 之后以便运维可覆盖误配；默认值兜底保证
 * 数据库尚未建表时也能启动。
 */
final class Config
{
    /** site_config 表的内存缓存，避免一次请求内反复查库 */
    private static ?array $siteConfig = null;

    /** site_config 是否已加载（没连数据库时置位，避免反复重试） */
    private static bool $siteConfigLoaded = false;

    private static ?Database $db = null;

    /** DB 读取失败时的降级标记，防止每次调用都触发异常 */
    private static bool $dbAvailable = true;

    /**
     * 注入数据库连接。未注入时只走 .env + 默认值。
     */
    public static function setDatabase(Database $db): void
    {
        self::$db = $db;
    }

    /**
     * 读取配置。
     *
     * .env 值为空字符串时视为「未定义」继续向下回落：
     * 让 `KEY=`（留空）成为「交由后台 site_config 管理」的约定写法，
     * 否则空值会以最高优先级盖掉后台保存的运营配置。
     *
     * @param string $key     配置键（对应 .env 变量名或 site_config.key）
     * @param mixed  $default 默认值
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $fromEnv = Env::get($key);
        if ($fromEnv !== null && $fromEnv !== '') {
            return $fromEnv;
        }

        $fromSite = self::loadSiteConfig();
        if (array_key_exists($key, $fromSite)) {
            return $fromSite[$key];
        }

        return $default;
    }

    public static function string(string $key, string $default = ''): string
    {
        $value = self::get($key, $default);

        return is_scalar($value) ? (string) $value : $default;
    }

    public static function int(string $key, int $default = 0): int
    {
        $value = self::get($key);
        if ($value === null || $value === '') {
            return $default;
        }

        return (int) $value;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $value = self::get($key);
        if ($value === null || $value === '') {
            return $default;
        }

        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
    }

    /**
     * 读取 JSON 配置（如 site_config 里存的数组）。
     *
     * @return array<mixed>
     */
    public static function json(string $key, array $default = []): array
    {
        $value = self::get($key);
        if (!is_string($value) || trim($value) === '') {
            return is_array($value) ? $value : $default;
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : $default;
    }

    /**
     * 写入 site_config（后台设置页用）。
     *
     * 只写数据库，不写 .env —— .env 是部署产物，程序不应改写它。
     */
    public static function set(string $key, string $value): bool
    {
        if (self::$db === null || !self::$dbAvailable) {
            return false;
        }

        try {
            // UPDATE + INSERT 两步而非 REPLACE：REPLACE 会先删后插，
            // 触发外键级联且是 SQLite 专有语法，不利于将来迁移 MySQL。
            $updated = self::$db->execute(
                'UPDATE site_config SET value = :value WHERE key = :key',
                [':value' => $value, ':key' => $key]
            );

            if ($updated === 0) {
                self::$db->execute(
                    'INSERT INTO site_config (key, value) VALUES (:key, :value)',
                    [':key' => $key, ':value' => $value]
                );
            }

            // 缓存失效，下次读取重新查库
            self::$siteConfigLoaded = false;
            self::$siteConfig = null;

            return true;
        } catch (\Throwable $e) {
            Logger::error('site_config 写入失败', ['key' => $key, 'error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * 数据库中的全部站点配置。
     *
     * @return array<string, string>
     */
    public static function allSiteConfig(): array
    {
        return self::loadSiteConfig();
    }

    /**
     * 清空缓存（后台改完配置后调用，或测试用）。
     */
    public static function flush(): void
    {
        self::$siteConfig = null;
        self::$siteConfigLoaded = false;
    }

    /**
     * @return array<string, string>
     */
    private static function loadSiteConfig(): array
    {
        if (self::$siteConfigLoaded) {
            return self::$siteConfig ?? [];
        }

        self::$siteConfigLoaded = true;
        self::$siteConfig = [];

        if (self::$db === null || !self::$dbAvailable) {
            return self::$siteConfig;
        }

        try {
            $rows = self::$db->fetchAll('SELECT key, value FROM site_config');
            foreach ($rows as $row) {
                if (isset($row['key'])) {
                    self::$siteConfig[(string) $row['key']] = (string) ($row['value'] ?? '');
                }
            }
        } catch (\Throwable $e) {
            // 表尚未创建（首次运行）属正常情况，降级但不反复重试
            self::$dbAvailable = false;
            Logger::debug('site_config 读取失败，降级为 .env + 默认值', ['error' => $e->getMessage()]);
        }

        return self::$siteConfig;
    }
}
