<?php
declare(strict_types=1);

namespace App\Core;

use Throwable;

/**
 * 文件日志
 *
 * 写入 var/logs/app-{Y-m-d}.log，按天分文件。
 *
 * 设计取舍：日志失败绝不抛异常。日志是诊断手段，若因磁盘满或权限问题
 * 让请求失败，等于用次要功能破坏主要功能。
 */
final class Logger
{
    public const EMERGENCY = 'EMERGENCY';
    public const ERROR     = 'ERROR';
    public const WARNING   = 'WARNING';
    public const INFO      = 'INFO';
    public const DEBUG     = 'DEBUG';

    private static string $dir = '';

    /** 日志目录是否可用（首次写入时探测，避免每次请求都试错） */
    private static ?bool $writable = null;

    public static function init(string $dir): void
    {
        self::$dir = $dir;
        self::$writable = null;
    }

    /**
     * @param array<string, mixed> $context
     */
    public static function log(string $level, string $message, array $context = []): void
    {
        if (!self::ensureWritable()) {
            return;
        }

        $line = sprintf(
            "[%s] [%s] %s%s\n",
            date('Y-m-d H:i:s'),
            $level,
            $message,
            $context === [] ? '' : ' ' . self::encodeContext($context)
        );

        // LOCK_EX 防多请求并发写同一行时交错撕裂
        @file_put_contents(self::filePath(), $line, FILE_APPEND | LOCK_EX);
    }

    /**
     * @param array<string, mixed> $context
     */
    public static function error(string $message, array $context = []): void
    {
        self::log(self::ERROR, $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public static function warning(string $message, array $context = []): void
    {
        self::log(self::WARNING, $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public static function info(string $message, array $context = []): void
    {
        self::log(self::INFO, $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public static function debug(string $message, array $context = []): void
    {
        // 生产环境不落 DEBUG 日志，避免日志体积失控
        if (!Env::isDebug()) {
            return;
        }

        self::log(self::DEBUG, $message, $context);
    }

    /**
     * 记录异常，含文件行号与堆栈。
     */
    public static function exception(Throwable $e, string $message = ''): void
    {
        self::error(
            ($message === '' ? '未捕获异常' : $message) . ': ' . $e->getMessage(),
            [
                'class' => $e::class,
                'file'  => $e->getFile() . ':' . $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]
        );
    }

    public static function filePath(): string
    {
        return self::$dir . DIRECTORY_SEPARATOR . 'app-' . date('Y-m-d') . '.log';
    }

    private static function ensureWritable(): bool
    {
        if (self::$writable !== null) {
            return self::$writable;
        }

        if (self::$dir === '') {
            self::$writable = false;
            return false;
        }

        if (!is_dir(self::$dir) && !@mkdir(self::$dir, 0775, true) && !is_dir(self::$dir)) {
            self::$writable = false;
            return false;
        }

        self::$writable = is_writable(self::$dir);
        return self::$writable;
    }

    /**
     * @param array<string, mixed> $context
     */
    private static function encodeContext(array $context): string
    {
        $encoded = json_encode(
            $context,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR
        );

        return $encoded === false ? '[context 编码失败]' : $encoded;
    }
}
