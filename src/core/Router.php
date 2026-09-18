<?php
declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * 路由
 *
 * 支持静态路径、命名参数（/tool/{id}）与可选中段（{page?}）。
 * 中间件以「路由组前缀」方式挂载，前台仅 Csrf，后台 Session → Auth → Role → Csrf。
 *
 * 为什么不用正则转义全路径：把 /tool/{id} 编译成 #^/tool/([^/]+)$# 这一形式
 * 实现简单且无回溯风险（参数段限定非斜杠），对本站路由规模足够。
 */
final class Router
{
    /**
     * @var array<int, array{
     *     method: string,
     *     pattern: string,
     *     regex: string,
     *     params: list<string>,
     *     handler: callable|array{0: class-string, 1: string},
     *     middleware: list<string>
     * }>
     */
    private array $routes = [];

    /** 当前生效的中间件（addGroup 时压栈） */
    private array $groupMiddleware = [];

    /** 当前分组路径前缀 */
    private string $groupPrefix = '';

    /** 路由表是否已导入，避免重复 require */
    private bool $loaded = false;

    /**
     * 注册 GET 路由。
     *
     * @param callable|array{0: class-string, 1: string} $handler
     * @param list<string> $middleware
     */
    public function get(string $pattern, callable|array $handler, array $middleware = []): void
    {
        $this->add('GET', $pattern, $handler, $middleware);
    }

    /**
     * @param callable|array{0: class-string, 1: string} $handler
     * @param list<string> $middleware
     */
    public function post(string $pattern, callable|array $handler, array $middleware = []): void
    {
        $this->add('POST', $pattern, $handler, $middleware);
    }

    /**
     * @param callable|array{0: class-string, 1: string} $handler
     * @param list<string> $middleware
     */
    public function put(string $pattern, callable|array $handler, array $middleware = []): void
    {
        $this->add('PUT', $pattern, $handler, $middleware);
    }

    /**
     * @param callable|array{0: class-string, 1: string} $handler
     * @param list<string> $middleware
     */
    public function delete(string $pattern, callable|array $handler, array $middleware = []): void
    {
        $this->add('DELETE', $pattern, $handler, $middleware);
    }

    /**
     * 注册路由组。
     *
     * @param list<string> $middleware
     */
    public function group(string $prefix, array $middleware, callable $callback): void
    {
        $prevPrefix = $this->groupPrefix;
        $prevMiddleware = $this->groupMiddleware;

        $this->groupPrefix = $prevPrefix . $prefix;
        $this->groupMiddleware = array_merge($prevMiddleware, $middleware);

        $callback($this);

        $this->groupPrefix = $prevPrefix;
        $this->groupMiddleware = $prevMiddleware;
    }

    /**
     * 导入路由表文件。只执行一次。
     */
    public function load(string $routesFile): void
    {
        if ($this->loaded) {
            return;
        }
        $this->loaded = true;

        if (!is_file($routesFile)) {
            throw new RuntimeException('路由表不存在: ' . $routesFile);
        }

        // 路由表通过 $router 变量访问本实例
        $router = $this;
        require $routesFile;
    }

    /**
     * 匹配路由并把路径参数写入请求对象。
     *
     * 只做匹配不做执行：中间件链需要在执行前介入，因此把「执行」交给
     * 调用方（bootstrap），路由本身保持无副作用。
     *
     * @return array{handler: callable|array{0: class-string, 1: string}, middleware: list<string>}
     * @throws RuntimeException 未匹配（NOT_FOUND / METHOD_NOT_ALLOWED）
     */
    public function dispatch(Request $request): array
    {
        $method = $request->method();
        $path = $request->path();

        $allowedMethods = [];

        foreach ($this->routes as $route) {
            if (preg_match($route['regex'], $path, $matches) !== 1) {
                continue;
            }

            // 路径匹配但方法不符：记录以便返回 405 而非 404
            if ($route['method'] !== $method) {
                $allowedMethods[] = $route['method'];
                continue;
            }

            array_shift($matches);
            foreach ($route['params'] as $index => $name) {
                if (isset($matches[$index]) && $matches[$index] !== '') {
                    $request->setAttribute($name, $matches[$index]);
                }
            }

            return [
                'handler'    => $route['handler'],
                'middleware' => $route['middleware'],
            ];
        }

        if ($allowedMethods !== []) {
            throw new RuntimeException('METHOD_NOT_ALLOWED:' . implode(',', array_unique($allowedMethods)));
        }

        throw new RuntimeException('NOT_FOUND');
    }

    /**
     * @return array<int, array{method: string, pattern: string}>
     */
    public function routes(): array
    {
        return array_map(
            static fn (array $r): array => ['method' => $r['method'], 'pattern' => $r['pattern']],
            $this->routes
        );
    }

    /**
     * @param callable|array{0: class-string, 1: string} $handler
     * @param list<string> $middleware
     */
    private function add(string $method, string $pattern, callable|array $handler, array $middleware): void
    {
        $pattern = $this->groupPrefix . $pattern;
        if ($pattern === '') {
            $pattern = '/';
        }

        // 去掉末尾斜杠（根除外），与 Request::path() 的规范化保持一致
        if ($pattern !== '/' && str_ends_with($pattern, '/')) {
            $pattern = rtrim($pattern, '/');
        }

        [$regex, $params] = $this->compile($pattern);

        $this->routes[] = [
            'method'     => $method,
            'pattern'    => $pattern,
            'regex'      => $regex,
            'params'     => $params,
            'handler'    => $handler,
            'middleware' => array_merge($this->groupMiddleware, $middleware),
        ];
    }

    /**
     * 把 /tool/{id} 编译为正则，并收集参数名顺序。
     *
     * 占位符会连同其**前导斜杠**一起被替换，这样可选段的斜杠也进入
     * 非捕获组，否则 /list/{page?} 会编译出 /list//x 这类错误正则。
     *
     * @return array{0: string, 1: list<string>}
     */
    private function compile(string $pattern): array
    {
        $params = [];

        $regex = preg_replace_callback(
            '#(/?)\{([a-zA-Z_][a-zA-Z0-9_]*)(\?)?\}#',
            static function (array $m) use (&$params): string {
                $params[] = $m[2];
                $optional = ($m[3] ?? '') === '?';

                // 参数段限定为非斜杠字符，天然阻断跨越多级路径的注入
                if ($optional) {
                    return $m[1] === '/'
                        ? '(?:/([^/]+))?'
                        : '([^/]+)?';
                }

                return $m[1] === '/' ? '/([^/]+)' : '([^/]+)';
            },
            $pattern
        );

        if ($regex === null) {
            throw new RuntimeException('路由编译失败: ' . $pattern);
        }

        return ['#^' . $regex . '$#', $params];
    }
}
