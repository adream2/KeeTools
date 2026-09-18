<?php
declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * 应用容器
 *
 * 持有全站共享实例（数据库连接、路径常量）。用静态属性而非依赖注入容器：
 * 本项目规模不需要 DI 容器的灵活度，但需要「取用方便」——
 * 控制器和服务层随时能拿到连接是刚需。
 *
 * 测试时可 setDb() 注入内存数据库替换。
 */
final class App
{
    private static ?Database $db = null;

    private static ?Database $statsDb = null;

    private static string $basePath = '';

    public static function setBasePath(string $path): void
    {
        self::$basePath = rtrim($path, '/\\');
    }

    /**
     * 拼接项目内绝对路径。
     *
     * @param string $sub 相对项目根的路径，如 'storage/app.db'
     */
    public static function path(string $sub = ''): string
    {
        if (self::$basePath === '') {
            throw new RuntimeException('App::$basePath 未初始化，须先调用 setBasePath()');
        }

        if ($sub === '') {
            return self::$basePath;
        }

        return self::$basePath . DIRECTORY_SEPARATOR
            . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ltrim($sub, '/\\'));
    }

    public static function setDb(Database $db): void
    {
        self::$db = $db;
        // Config 的三级读取依赖业务库，这里顺带注入，避免调用方两处配置
        Config::setDatabase($db);
    }

    /**
     * 业务库连接。
     *
     * @throws RuntimeException 未初始化时抛出，明确暴露装配遗漏
     */
    public static function db(): Database
    {
        if (self::$db === null) {
            throw new RuntimeException('业务数据库未初始化');
        }

        return self::$db;
    }

    public static function hasDb(): bool
    {
        return self::$db !== null;
    }

    public static function setStatsDb(Database $db): void
    {
        self::$statsDb = $db;
    }

    /**
     * 统计库连接（与业务库物理隔离）。
     *
     * @throws RuntimeException
     */
    public static function statsDb(): Database
    {
        if (self::$statsDb === null) {
            throw new RuntimeException('统计数据库未初始化');
        }

        return self::$statsDb;
    }

    public static function hasStatsDb(): bool
    {
        return self::$statsDb !== null;
    }

    /**
     * 重置容器（测试用）。
     */
    public static function reset(): void
    {
        self::$db = null;
        self::$statsDb = null;
    }
}
