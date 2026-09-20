<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\App;

/**
 * 第三方素材版权说明（唯一真源：src/data/third-party.json）
 *
 * /about 页由此服务驱动渲染，THIRD-PARTY-LICENSES.md 由
 * scripts/gen_licenses.py 从同一 JSON 生成 —— 新增素材只改 JSON 一处。
 * JSON 缺失 / 损坏时返回空结构（页面降级为提示，不 500）。
 *
 * 注意：JSON 位于 src/data/（随应用代码部署）；assets-src/ 是构建源目录，
 * 不进入生产环境（2026-09-20 迁移，此前放 assets-src/ 导致线上 /about 降级）。
 *
 * @phpstan-type Item array{name: string, source: string, license: string, usage: string}
 * @phpstan-type Category array{id: string, title: string, items: list<Item>, notes: list<string>}
 */
final class ThirdPartyNotices
{
    /**
     * 读取全部登记条目。
     *
     * @return array{categories: list<Category>}
     */
    public static function all(): array
    {
        $file = App::path('src/data/third-party.json');
        if (!is_file($file)) {
            return ['categories' => []];
        }

        $raw = file_get_contents($file);
        if ($raw === false) {
            return ['categories' => []];
        }

        try {
            $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return ['categories' => []];
        }

        if (!is_array($data) || !is_array($data['categories'] ?? null)) {
            return ['categories' => []];
        }

        $categories = [];
        foreach ($data['categories'] as $category) {
            if (!is_array($category) || !is_string($category['title'] ?? null)) {
                continue;
            }

            $items = [];
            foreach (($category['items'] ?? []) as $item) {
                if (!is_array($item) || !is_string($item['name'] ?? null)) {
                    continue;
                }
                $items[] = [
                    'name'    => (string) $item['name'],
                    'source'  => (string) ($item['source'] ?? ''),
                    'license' => (string) ($item['license'] ?? ''),
                    'usage'   => (string) ($item['usage'] ?? ''),
                ];
            }

            $notes = [];
            foreach (($category['notes'] ?? []) as $note) {
                if (is_string($note) && trim($note) !== '') {
                    $notes[] = $note;
                }
            }

            $categories[] = [
                'id'     => (string) ($category['id'] ?? ''),
                'title'  => (string) $category['title'],
                'items'  => $items,
                'notes'  => $notes,
            ];
        }

        return ['categories' => $categories];
    }

    /** 是否至少有一条登记（决定关于页显示登记表还是降级提示）。 */
    public static function hasEntries(): bool
    {
        foreach (self::all()['categories'] as $category) {
            if ($category['items'] !== [] || $category['notes'] !== []) {
                return true;
            }
        }

        return false;
    }
}
