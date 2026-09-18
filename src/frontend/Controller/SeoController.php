<?php
declare(strict_types=1);

namespace App\Frontend\Controller;

use App\Core\App;
use App\Core\Request;
use App\Core\Response;
use App\Services\ToolRepository;

/**
 * SEO 端点（P4 §三）：/sitemap.xml 与 /robots.txt
 *
 * 为什么用动态路由而不是 public/ 下的静态文件：
 *   站点根地址（域名 / http 与 https / 是否子目录）来自运行时配置，
 *   静态文件无法自适应；且归档版本页（/tool/{id}/v/*）与搜索页必须
 *   进 noindex 名单，集中在一处维护才不会漏。
 *
 * 注意：生产 Web 根为 public/，若在那里放了同名静态文件会覆盖本路由。
 */
final class SeoController
{
    public function robots(Request $request): Response
    {
        $base = rtrim(site_url(), '/');

        $lines = [
            'User-agent: *',
            'Allow: /',
            '',
            '# 后台与接口不进索引',
            'Disallow: /admin',
            'Disallow: /api/',
            '',
            '# 站内搜索结果是动态页，避免垃圾页被收录（页面本身也带 noindex）',
            'Disallow: /search',
            '',
            '# 网盘中间页与下载端点属转化环节，无独立内容价值',
            'Disallow: /netdisk/',
            'Disallow: /download/',
            '',
            '# 旧版本归档一律不进索引（/tool/{id}/v/{version}）',
            'Disallow: /tool/*/v/',
            '',
        ];

        if ($base !== '') {
            $lines[] = 'Sitemap: ' . $base . '/sitemap.xml';
        }

        return Response::text(implode("\n", $lines) . "\n");
    }

    public function sitemap(Request $request): Response
    {
        $urls = [];

        $base = rtrim(site_url(), '/');
        if ($base === '') {
            // 无 Host 上下文（CLI / 反代异常）时不输出半成品地图
            return Response::text("<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\"></urlset>\n")
                ->withHeader('Content-Type', 'application/xml; charset=UTF-8');
        }

        $urls[] = ['loc' => '/', 'lastmod' => date('Y-m-d'), 'priority' => '1.0'];
        $urls[] = ['loc' => '/tools', 'lastmod' => date('Y-m-d'), 'priority' => '0.9'];

        if (App::hasDb()) {
            $repository = new ToolRepository();

            // 分类页（学段 + 学科）
            foreach ($repository->stageCards() as $stage) {
                $urls[] = [
                    'loc'      => '/category/' . $stage['slug'],
                    'lastmod'  => date('Y-m-d'),
                    'priority' => '0.8',
                ];
                foreach ($stage['subjects'] as $subject) {
                    $urls[] = [
                        'loc'      => '/category/' . $subject['slug'],
                        'lastmod'  => date('Y-m-d'),
                        'priority' => '0.7',
                    ];
                }
            }

            // 工具详情页（仅已上架；归档版本 /tool/{id}/v/* 刻意排除）
            foreach ($repository->latest(1000) as $tool) {
                $urls[] = [
                    'loc'      => '/tool/' . rawurlencode((string) $tool['tool_id']),
                    'lastmod'  => (string) $tool['updated_at'],
                    'priority' => $tool['is_featured'] ? '0.9' : '0.7',
                ];
            }
        }

        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n"
            . "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";
        foreach ($urls as $item) {
            $xml .= "  <url>\n"
                . '    <loc>' . self::xml($base . $item['loc']) . "</loc>\n"
                . '    <lastmod>' . self::xml($item['lastmod']) . "</lastmod>\n"
                . '    <priority>' . $item['priority'] . "</priority>\n"
                . "  </url>\n";
        }
        $xml .= "</urlset>\n";

        return Response::text($xml)->withHeader('Content-Type', 'application/xml; charset=UTF-8');
    }

    private static function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
