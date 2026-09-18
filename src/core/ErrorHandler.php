<?php
declare(strict_types=1);

namespace App\Core;

use ErrorException;
use Throwable;

/**
 * 错误与异常处理
 *
 * 统一接管 PHP 错误、未捕获异常与致命错误，保证：
 * - 所有错误都落日志（生产环境不看屏幕也留有痕迹）
 * - 生产环境不向用户泄露路径、SQL、堆栈
 * - 调试模式显示完整信息，便于开发
 *
 * 致命错误（E_ERROR 等）无法被 set_error_handler 捕获，须注册
 * shutdown 函数用 error_get_last() 兜底，否则白屏且无日志。
 */
final class ErrorHandler
{
    /** 由本处理器接管、转为异常的错误级别 */
    private const FATAL_LEVELS = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];

    public static function register(): void
    {
        error_reporting(E_ALL);

        // 关闭 PHP 自带输出：错误要么进日志，要么由我们渲染统一页面
        ini_set('display_errors', '0');
        ini_set('log_errors', '0');

        set_error_handler([self::class, 'handleError']);
        set_exception_handler([self::class, 'handleException']);
        register_shutdown_function([self::class, 'handleShutdown']);
    }

    /**
     * 把警告/通知转成异常，避免被静默忽略。
     *
     * 用 @ 抑制的错误（error_reporting() 为 0）不转换：调用方已明确表示
     * 该错误可接受（如探测目录是否可写）。
     *
     * @throws ErrorException
     */
    public static function handleError(int $level, string $message, string $file = '', int $line = 0): bool
    {
        if ((error_reporting() & $level) === 0) {
            return false;
        }

        throw new ErrorException($message, 0, $level, $file, $line);
    }

    public static function handleException(Throwable $e): void
    {
        Logger::exception($e, '未捕获异常');

        // 已开始输出时无法再改状态码/渲染页面，只能补记日志
        if (headers_sent()) {
            return;
        }

        self::render($e);
    }

    /**
     * 捕获致命错误。
     */
    public static function handleShutdown(): void
    {
        $error = error_get_last();
        if ($error === null || !in_array($error['type'], self::FATAL_LEVELS, true)) {
            return;
        }

        Logger::log(Logger::EMERGENCY, '致命错误', [
            'type'    => $error['type'],
            'message' => $error['message'],
            'file'    => $error['file'] . ':' . $error['line'],
        ]);

        if (headers_sent()) {
            return;
        }

        $e = new ErrorException($error['message'], 0, $error['type'], $error['file'], $error['line']);
        self::render($e);
    }

    /**
     * 渲染错误页。调试模式显示堆栈，生产只给友好提示。
     */
    public static function render(Throwable $e, int $status = 500): void
    {
        http_response_code($status);
        header('Content-Type: text/html; charset=UTF-8');

        // 优先级：自定义错误页模板 → 内置兜底 HTML。
        // 模板可能本身出错（如视图目录缺失），故兜底不可少。
        if (View::exists('pages/error')) {
            try {
                echo View::render('pages/error', [
                    'status'      => $status,
                    'message'     => $e->getMessage(),
                    'exception'   => $e,
                    'showDetail'  => Env::isDebug(),
                ], null);

                return;
            } catch (Throwable $inner) {
                Logger::exception($inner, '渲染错误页失败');
            }
        }

        echo self::fallbackHtml($e, $status);
    }

    /**
     * 内置兜底错误页。不依赖任何模板，纯 PHP 拼接。
     */
    private static function fallbackHtml(Throwable $e, int $status): string
    {
        $title = '服务出错了';
        $body = '服务器遇到问题，请稍后再试。';

        if (Env::isDebug()) {
            $body = '<pre style="white-space:pre-wrap;word-break:break-all;">'
                . Security::escape($e::class . ': ' . $e->getMessage()) . "\n\n"
                . Security::escape($e->getFile() . ':' . $e->getLine()) . "\n\n"
                . Security::escape($e->getTraceAsString())
                . '</pre>';
        }

        return '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="UTF-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>' . Security::escape($title) . '</title></head>'
            . '<body style="font-family:system-ui,sans-serif;max-width:48rem;margin:4rem auto;padding:0 1rem;color:#1f2937;">'
            . '<h1 style="font-size:1.5rem;">' . Security::escape($title) . ' (' . $status . ')</h1>'
            . '<div style="color:#6b7280;line-height:1.7;">' . $body . '</div>'
            . '</body></html>';
    }
}
