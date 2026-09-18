<?php
declare(strict_types=1);

/**
 * PHP 内置服务器路由脚本（**仅本地开发用，生产环境不使用**）
 *
 * 为什么需要它：`php -S` 指定 router 脚本后，所有请求（含 .css/.js）
 * 都会进入该脚本。若不特判静态文件，样式与脚本将无法加载。
 * Nginx / Apache 不会这样，它们会直接返回磁盘上的静态文件。
 *
 * 用法：
 *   php -S 127.0.0.1:8000 -t public public/router.php
 *   （不带 public/router.php 则 404 页面会由内置服务器返回，
 *     看不到本站的错误页，故推荐带上）
 */

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$path = is_string($path) ? $path : '/';

$publicDir = realpath(__DIR__);
if ($publicDir === false) {
    http_response_code(500);
    exit('public 目录不可用');
}

$decoded = rawurldecode($path);
$candidate = $publicDir . str_replace('/', DIRECTORY_SEPARATOR, $decoded);

// 必须 realpath 后校验前缀，而非检查字符串中是否含 '..'：
// 后者可被编码变形（%2e%2e）、多重斜杠等方式绕过，
// 一旦绕过即可读到 public/ 之外的 .env、src/ 等敏感文件。
$real = realpath($candidate);

if ($real !== false && is_file($real)
    && str_starts_with($real, $publicDir . DIRECTORY_SEPARATOR)
) {
    // 点文件（.env / .git 等）一律不通过内置服务器暴露
    if (str_starts_with(basename($real), '.')) {
        http_response_code(403);
        exit('Forbidden');
    }

    // 交还内置服务器直接输出，Content-Type 由其按扩展名推断
    return false;
}

// 其余请求交给前端控制器
require __DIR__ . '/index.php';
