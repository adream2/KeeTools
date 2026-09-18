<?php
declare(strict_types=1);

namespace App\Core;

/**
 * HTTP 响应
 *
 * 控制器返回本对象，由 public/index.php 统一 send()。
 * 这样控制器不必直接 echo / header()，便于测试与中断流程。
 */
final class Response
{
    /** @var array<string, string> 待发送的响应头 */
    private array $headers = [];

    private int $status = 200;

    private string $content = '';

    /** 是否已发送（防止重复输出） */
    private bool $sent = false;

    public function __construct(string $content = '', int $status = 200)
    {
        $this->content = $content;
        $this->status = $status;
    }

    /**
     * HTML 响应。
     */
    public static function html(string $content, int $status = 200): self
    {
        return (new self($content, $status))
            ->withHeader('Content-Type', 'text/html; charset=UTF-8');
    }

    /**
     * JSON 响应。
     *
     * JSON_UNESCAPED_UNICODE 保证中文可读；
     * JSON_HEX_TAG/AMP/APOS/QUOT 保证嵌入 HTML 的 <script> 中也不会提前闭合。
     *
     * @param mixed $data
     */
    public static function json(mixed $data, int $status = 200): self
    {
        $flags = JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
            | JSON_HEX_TAG
            | JSON_HEX_AMP
            | JSON_HEX_APOS
            | JSON_HEX_QUOT;

        $encoded = json_encode($data, $flags);
        if ($encoded === false) {
            $encoded = '{"error":"响应序列化失败"}';
            $status = 500;
        }

        return (new self($encoded, $status))
            ->withHeader('Content-Type', 'application/json; charset=UTF-8');
    }

    /**
     * 纯文本响应。
     */
    public static function text(string $content, int $status = 200): self
    {
        return (new self($content, $status))
            ->withHeader('Content-Type', 'text/plain; charset=UTF-8');
    }

    /**
     * 重定向。默认 302 临时跳转，301 会永久缓存，仅静态化迁移时使用。
     */
    public static function redirect(string $url, int $status = 302): self
    {
        return (new self('', $status))->withHeader('Location', $url);
    }

    /**
     * 文件下载。文件名走 basename 防头部注入与路径泄露。
     */
    public static function download(string $filePath, ?string $filename = null): self
    {
        if (!is_file($filePath) || !is_readable($filePath)) {
            return new self('文件不存在', 404);
        }

        $name = basename($filename ?? $filePath);
        // 只有 ASCII 才放入标准参数，中文名用 RFC 5987 的 filename*
        $ascii = preg_replace('/[^\x20-\x7E]/', '_', $name) ?? 'download';

        return (new self('', 200))
            ->withHeader('Content-Type', 'application/octet-stream')
            ->withHeader(
                'Content-Disposition',
                'attachment; filename="' . $ascii . '"; filename*=UTF-8\'\'' . rawurlencode($name)
            )
            ->withHeader('Content-Length', (string) filesize($filePath))
            ->withHeader('X-Send-File', $filePath);
    }

    public function withHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;

        return $this;
    }

    public function withStatus(int $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function status(): int
    {
        return $this->status;
    }

    public function content(): string
    {
        return $this->content;
    }

    public function setContent(string $content): self
    {
        $this->content = $content;

        return $this;
    }

    /**
     * @return array<string, string>
     */
    public function headers(): array
    {
        return $this->headers;
    }

    /**
     * 发送响应。文件下载走 readfile 以支持大文件流式输出。
     */
    public function send(): void
    {
        if ($this->sent) {
            return;
        }
        $this->sent = true;

        // 已有输出（如 BOM 或空白）会导致 header 失败，此处不掩盖问题
        if (!headers_sent()) {
            http_response_code($this->status);
            foreach ($this->headers as $name => $value) {
                header($name . ': ' . $value, true);
            }
            // 站点自身安全头：防 MIME 嗅探、防点击劫持、限制引用来源
            header('X-Content-Type-Options: nosniff', true);
            header('X-Frame-Options: SAMEORIGIN', true);
            header('Referrer-Policy: strict-origin-when-cross-origin', true);
        }

        $sendFile = $this->headers['X-Send-File'] ?? null;
        if ($sendFile !== null && is_file($sendFile)) {
            $handle = fopen($sendFile, 'rb');
            if ($handle !== false) {
                while (!feof($handle)) {
                    $chunk = fread($handle, 8192);
                    if ($chunk === false) {
                        break;
                    }
                    echo $chunk;
                }
                fclose($handle);
            }

            return;
        }

        echo $this->content;
    }
}
