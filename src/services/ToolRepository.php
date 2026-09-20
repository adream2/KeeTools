<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\App;
use App\Core\Database;
use RuntimeException;

/**
 * 前台工具只读查询
 *
 * 与 ToolScanner（写侧）对应：这里是前台列表 / 详情 / 搜索的统一读出口。
 * 只输出已上架（is_published = 1）的工具；JSON 列在此统一解码，
 * 模板层拿到的直接是数组，避免每个视图重复 decode。
 */
final class ToolRepository
{
    /** 列表 / 卡片场景的公共字段 */
    private const CARD_FIELDS =
        'tool_id, title, description, version, type, family, grade_range, subjects, tags, requires, author,
         entry, single_file, offline, screen, stats_enabled, license, is_featured, dir_path, updated_at';

    private ?Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db;
    }

    /**
     * 推荐工具（后台标记 is_featured）。
     *
     * @return list<array<string, mixed>>
     */
    public function featured(int $limit = 6): array
    {
        return $this->hydrateAll($this->queryAll(
            'WHERE is_published = 1 AND is_featured = 1
             ORDER BY sort_order ASC, updated_at DESC',
            $limit
        ));
    }

    /**
     * 最新 / 全部工具（按更新时间倒序）。
     *
     * @return list<array<string, mixed>>
     */
    public function latest(int $limit = 60): array
    {
        return $this->hydrateAll($this->queryAll(
            'WHERE is_published = 1
             ORDER BY is_featured DESC, sort_order ASC, updated_at DESC, tool_id ASC',
            $limit
        ));
    }

    /**
     * 按关键词搜索（工具名 / 简介 / 标签 / 学科）。$q 为空 = 全部工具。
     *
     * @return list<array<string, mixed>>
     */
    public function search(string $q, int $limit = 60): array
    {
        $q = trim($q);
        if ($q === '') {
            return $this->latest($limit);
        }

        // LIKE 通配符转义，防止用户输入 % _ 被当通配符
        $like = '%' . strtr($q, ['\\' => '\\\\', '%' => '\\%', '_' => '\\_']) . '%';

        $rows = $this->db()->fetchAll(
            "SELECT " . self::CARD_FIELDS . " FROM tools
             WHERE is_published = 1
               AND (title LIKE :like ESCAPE '\\'
                    OR description LIKE :like ESCAPE '\\'
                    OR tags LIKE :like ESCAPE '\\'
                    OR subjects LIKE :like ESCAPE '\\')
             ORDER BY is_featured DESC, updated_at DESC
             LIMIT " . (int) $limit,
            [':like' => $like]
        );

        return $this->hydrateAll($rows);
    }

    /**
     * 按分类 slug 查工具。一级学段页 = 含其下全部学科分类的工具；
     * 二级学科页 = 精确匹配该分类。
     *
     * @return array{
     *     category: array<string, mixed>,
     *     tools: list<array<string, mixed>>,
     *     chips: list<array<string, mixed>>,
     *     total: int,
     * }|null 分类不存在时返回 null
     */
    public function byCategorySlug(string $slug): ?array
    {
        $category = $this->db()->fetch(
            'SELECT id, name, slug, parent_id, level, icon FROM categories WHERE slug = :slug',
            [':slug' => $slug]
        );
        if ($category === null) {
            return null;
        }

        $category['id'] = (int) $category['id'];
        $category['parent_id'] = $category['parent_id'] !== null ? (int) $category['parent_id'] : null;
        $category['level'] = (int) $category['level'];
        $categoryId = $category['id'];

        // chips：一级学段页 = 下属学科；二级学科页 = 同学段的兄弟学科（含自身，用于高亮）
        if ($category['level'] === 1) {
            $chips = $this->db()->fetchAll(
                'SELECT id, name, slug FROM categories WHERE parent_id = :pid ORDER BY sort_order ASC',
                [':pid' => $categoryId]
            );
        } else {
            $chips = $this->db()->fetchAll(
                'SELECT id, name, slug FROM categories
                 WHERE parent_id = :pid ORDER BY sort_order ASC',
                [':pid' => $category['parent_id']]
            );
        }
        foreach ($chips as &$chip) {
            $chip['id'] = (int) $chip['id'];
        }
        unset($chip);

        // 一级学段页要把它下面所有学科分类的工具一并展示
        $categoryIds = [$categoryId];
        if ($category['level'] === 1) {
            foreach ($chips as $chip) {
                $categoryIds[] = $chip['id'];
            }
        }

        $placeholders = implode(', ', array_map(
            static fn (int $i): string => ':cid' . $i,
            array_keys($categoryIds)
        ));
        $params = [];
        foreach ($categoryIds as $i => $cid) {
            $params[':cid' . $i] = $cid;
        }

        $rows = $this->db()->fetchAll(
            'SELECT ' . self::CARD_FIELDS . ' FROM tools
             WHERE is_published = 1
               AND tool_id IN (
                   SELECT tool_id FROM tool_category
                   WHERE category_id IN (' . $placeholders . ')
               )
             ORDER BY is_featured DESC, updated_at DESC, tool_id ASC
             LIMIT 200',
            $params
        );

        $tools = $this->hydrateAll($rows);

        // chips 计数（学段页含子分类直接挂载的工具），一次聚合查询
        $allIds = array_values(array_unique(array_merge(
            $categoryIds,
            array_column($chips, 'id')
        )));
        $allPlaceholders = implode(', ', array_map(
            static fn (int $i): string => ':aid' . $i,
            array_keys($allIds)
        ));
        $allParams = [];
        foreach ($allIds as $i => $cid) {
            $allParams[':aid' . $i] = $cid;
        }

        $countMap = [];
        foreach ($this->db()->fetchAll(
            'SELECT category_id, COUNT(*) AS n
             FROM tool_category
             WHERE category_id IN (' . $allPlaceholders . ')
             GROUP BY category_id',
            $allParams
        ) as $row) {
            $countMap[(int) $row['category_id']] = (int) $row['n'];
        }
        foreach ($chips as &$chip) {
            $chip['count'] = $countMap[$chip['id']] ?? 0;
        }
        unset($chip);

        $total = 0;
        foreach ($categoryIds as $cid) {
            $total += $countMap[$cid] ?? 0;
        }

        return [
            'category' => $category,
            'tools'    => $tools,
            'chips'    => $chips,
            'total'    => $total,
        ];
    }

    /**
     * 首页「按学段浏览」数据：三个学段卡片 + 各自学科 chips（含计数）。
     *
     * @return list<array{name: string, slug: string, icon: ?string, count: int, subjects: list<array<string, mixed>>}>
     */
    public function stageCards(): array
    {
        $stages = $this->db()->fetchAll(
            'SELECT id, name, slug, icon FROM categories WHERE level = 1 ORDER BY sort_order ASC'
        );
        if ($stages === []) {
            return [];
        }

        // 一次性取全部分类计数，避免 N+1
        $countMap = [];
        foreach ($this->db()->fetchAll(
            'SELECT category_id, COUNT(*) AS n
             FROM tool_category
             GROUP BY category_id'
        ) as $row) {
            $countMap[(int) $row['category_id']] = (int) $row['n'];
        }

        $cards = [];
        foreach ($stages as $stage) {
            $stageId = (int) $stage['id'];
            $subjects = $this->db()->fetchAll(
                'SELECT id, name, slug FROM categories WHERE parent_id = :pid ORDER BY sort_order ASC',
                [':pid' => $stageId]
            );

            $count = $countMap[$stageId] ?? 0;
            $subjectList = [];
            foreach ($subjects as $subject) {
                $n = $countMap[(int) $subject['id']] ?? 0;
                $count += $n;
                $subjectList[] = [
                    'name'  => (string) $subject['name'],
                    'slug'  => (string) $subject['slug'],
                    'count' => $n,
                ];
            }

            $cards[] = [
                'name'     => (string) $stage['name'],
                'slug'     => (string) $stage['slug'],
                'icon'     => $stage['icon'] !== null ? (string) $stage['icon'] : null,
                'count'    => $count,
                'subjects' => $subjectList,
            ];
        }

        return $cards;
    }

    /**
     * 工具详情。
     *
     * @return array<string, mixed>|null 未上架或不存在返回 null
     */
    public function find(string $toolId): ?array
    {
        $row = $this->db()->fetch(
            'SELECT ' . self::CARD_FIELDS . ', created_at, meta_mismatch
             FROM tools WHERE tool_id = :id AND is_published = 1',
            [':id' => $toolId]
        );

        return $row === null ? null : $this->hydrate($row);
    }

    /**
     * 后台用：不过滤上架状态（未上架工具也要能编辑）。
     *
     * @return array<string, mixed>|null
     */
    public function findAny(string $toolId): ?array
    {
        $row = $this->db()->fetch(
            'SELECT ' . self::CARD_FIELDS . ', created_at, meta_mismatch, is_published
             FROM tools WHERE tool_id = :id',
            [':id' => $toolId]
        );

        return $row === null ? null : $this->hydrate($row);
    }

    /**
     * 工具所属分类（面包屑用：取第一个学段级分类）。
     *
     * @return array{name: string, slug: string}|null
     */
    public function primaryStageForTool(string $toolId): ?array
    {
        $row = $this->db()->fetch(
            'SELECT c.name, c.slug
             FROM tool_category tc
             JOIN categories c ON c.id = tc.category_id
             WHERE tc.tool_id = :id AND c.level = 1
             ORDER BY c.sort_order ASC
             LIMIT 1',
            [':id' => $toolId]
        );

        return $row === null ? null : ['name' => (string) $row['name'], 'slug' => (string) $row['slug']];
    }

    /**
     * 同 family 的相关工具（同一算法的不同花样）。
     *
     * @return list<array<string, mixed>>
     */
    public function relatedByFamily(string $family, string $excludeToolId, int $limit = 3): array
    {
        $rows = $this->db()->fetchAll(
            'SELECT ' . self::CARD_FIELDS . ' FROM tools
             WHERE is_published = 1 AND family = :family AND tool_id != :exclude
             ORDER BY updated_at DESC
             LIMIT ' . (int) $limit,
            [':family' => $family, ':exclude' => $excludeToolId]
        );

        return $this->hydrateAll($rows);
    }

    /**
     * 工具数增长曲线（P4 §四）：近 N 个月的新增数与累计数。
     *
     * 数据源是 tools.created_at（扫描入库日期），不依赖统计库 —— 即使
     * 从未产生任何事件，也能看出"工具集在长大"。
     *
     * @return list<array{month: string, added: int, total: int}>
     */
    public function growthByMonth(int $months = 12): array
    {
        $months = max(1, min(36, $months));

        $map = [];
        foreach ($this->db()->fetchAll(
            'SELECT substr(created_at, 1, 7) AS ym, COUNT(*) AS n FROM tools GROUP BY ym'
        ) as $row) {
            $map[(string) $row['ym']] = (int) $row['n'];
        }

        $cursor = new \DateTimeImmutable(date('Y-m-01'));
        $cursor = $cursor->modify('-' . ($months - 1) . ' months');

        // 起始月之前的存量，作为累计基数
        $running = 0;
        $startMonth = $cursor->format('Y-m');
        foreach ($map as $ym => $n) {
            if ($ym < $startMonth) {
                $running += $n;
            }
        }

        $out = [];
        for ($i = 0; $i < $months; $i++) {
            $ym = $cursor->format('Y-m');
            $added = $map[$ym] ?? 0;
            $running += $added;
            $out[] = ['month' => $ym, 'added' => $added, 'total' => $running];
            $cursor = $cursor->modify('+1 month');
        }

        return $out;
    }

    /**
     * 学科工具数分布（P4 §四）：二级分类维度，只看已上架工具。
     *
     * @return list<array{name: string, slug: string, count: int}>
     */
    public function subjectDistribution(int $limit = 40): array
    {
        $rows = $this->db()->fetchAll(
            'SELECT c.name, c.slug, COUNT(DISTINCT tc.tool_id) AS n
             FROM categories c
             JOIN tool_category tc ON tc.category_id = c.id
             JOIN tools t ON t.tool_id = tc.tool_id AND t.is_published = 1
             WHERE c.level = 2
             GROUP BY c.id, c.name, c.slug
             ORDER BY n DESC, c.sort_order ASC
             LIMIT ' . (int) max(1, $limit)
        );

        return array_map(
            static fn (array $r): array => [
                'name'  => (string) $r['name'],
                'slug'  => (string) $r['slug'],
                'count' => (int) $r['n'],
            ],
            $rows
        );
    }

    /**
     * 已上架工具 id → 标题（零使用工具判定用）。
     *
     * @return array<string, string>
     */
    public function publishedTitles(): array
    {
        $out = [];
        foreach ($this->db()->fetchAll(
            'SELECT tool_id, title FROM tools WHERE is_published = 1 ORDER BY title ASC'
        ) as $row) {
            $out[(string) $row['tool_id']] = (string) $row['title'];
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function hydrate(array $row): array
    {
        foreach (['grade_range', 'subjects', 'tags', 'requires'] as $key) {
            $decoded = json_decode((string) ($row[$key] ?? '[]'), true);
            $row[$key] = is_array($decoded) ? $decoded : [];
        }
        $row['is_featured'] = (int) $row['is_featured'] === 1;
        $row['single_file'] = (int) $row['single_file'] === 1;
        $row['offline'] = (int) $row['offline'] === 1;
        $row['stats_enabled'] = (int) $row['stats_enabled'] === 1;
        if (isset($row['meta_mismatch'])) {
            $row['meta_mismatch'] = (int) $row['meta_mismatch'] === 1;
        }

        return $row;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private function hydrateAll(array $rows): array
    {
        return array_map([$this, 'hydrate'], $rows);
    }

    private function queryAll(string $suffix, int $limit): array
    {
        return $this->db()->fetchAll(
            'SELECT ' . self::CARD_FIELDS . ' FROM tools ' . $suffix . ' LIMIT ' . (int) $limit
        );
    }

    private function db(): Database
    {
        if ($this->db !== null) {
            return $this->db;
        }
        if (!App::hasDb()) {
            throw new RuntimeException('数据库未初始化');
        }

        return App::db();
    }
}
