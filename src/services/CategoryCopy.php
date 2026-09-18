<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;

/**
 * 分类页导语文案（P4 §三）
 *
 * 为什么需要它：空的工具列表页对搜索引擎与访客都没有价值（SEO 垃圾页）。
 * 每个分类页必须有一段**说明这个学科有哪些工具、怎么用**的导语。
 *
 * 文案策略：
 *   1. 后台可在 site_config 用 `category_intro.{slug}` 逐分类覆盖（运营主导）
 *   2. 未覆盖时按「学段 × 学科」场景词表程序化生成 —— 保证每个分类文案唯一，
 *      且不依赖人工为 40+ 个分类逐条撰文
 *
 * 场景词表是**运营资产**：新增学科/学段时同步补充这里即可。
 */
final class CategoryCopy
{
    /** 学科 → 课堂常见场景（生成导语用，越贴近真实课堂越好） */
    private const SUBJECT_SCENES = [
        'chinese'    => ['生字听写', '拼音识字', '课文朗读', '古诗背诵', '阅读理解讲评'],
        'math'       => ['口算训练', '分数与小数', '几何图形演示', '函数图像', '统计与概率'],
        'english'    => ['字母与音标', '单词认读', '拼写练习', '听力跟读', '课堂口语互动'],
        'science'    => ['观察记录', '实验演示', '天气与季节', '植物生长', '简单电路'],
        'physics'    => ['公式速查', '单位换算', '实验数据记录', '受力与运动演示', '电路连接'],
        'chemistry'  => ['元素周期表查阅', '化学式与化合价', '实验仪器识别', '化学方程式', '实验室安全'],
        'biology'    => ['细胞结构', '显微镜操作', '人体系统', '生态系统', '生物分类'],
        'politics'   => ['时政素材', '时间轴梳理', '观点辨析', '课堂辩论', '法治知识'],
        'history'    => ['时间轴梳理', '历史地图', '史料分析', '朝代对照', '人物评述'],
        'geography'  => ['地图填空', '气候类型', '地形判读', '经纬网练习', '区域对比'],
        'it'         => ['键盘操作', '算法思维', '信息检索', '数据表格', '演示文稿'],
        'pe'         => ['队列队形', '计时计分', '动作要领', '体能测试记录', '赛事编排'],
        'music'      => ['节奏训练', '简谱视唱', '乐理速查', '听音辨调', '合唱排练'],
        'art'        => ['色彩搭配', '构图练习', '美术鉴赏', '素描辅助', '课堂涂鸦'],
    ];

    /** 学段 → 教学侧重（生成导语用） */
    private const STAGE_FOCUS = [
        'primary' => '以直观、好玩、能动手为主，重点是把抽象概念变成看得见的东西',
        'junior'  => '以概念建立与实验观察为主，帮助学生把课本知识和现象对上号',
        'senior'  => '以原理推导与数据支撑为主，服务于复习、讲评和课堂演示',
    ];

    private function __construct()
    {
    }

    /**
     * 分类页导语。
     *
     * @param array<string, mixed> $category 分类行（含 name / slug / level / parent_id）
     * @param int $total 该分类下工具数
     * @param string|null $parentName 一级学段名（二级学科页拼成「小学数学」这类长尾词）
     */
    public static function intro(array $category, int $total, ?string $parentName = null): string
    {
        $slug = (string) ($category['slug'] ?? '');
        $name = (string) ($category['name'] ?? '');

        $custom = trim(Config::string('category_intro.' . $slug));
        if ($custom !== '') {
            return $custom;
        }
        if ($name === '') {
            return '';
        }

        $count = $total > 0
            ? "共收录 {$total} 个免费课堂工具"
            : '工具正在陆续上线';

        $level = (int) ($category['level'] ?? 2);

        if ($level === 1) {
            $focus = self::STAGE_FOCUS[$slug] ?? '覆盖日常备课与课堂演示的常见需求';
            return "{$name}教学工具合集，{$count}，{$focus}。"
                . '所有工具免安装、断网可用，用浏览器打开就能直接投到大屏上，'
                . '也可以按学科筛选，找到最贴合当前课时的那个。';
        }

        // 二级学科页：从 slug 反推学科段（junior-physics → physics）
        $subjectSlug = str_contains($slug, '-') ? substr($slug, strpos($slug, '-') + 1) : $slug;
        $scenes = self::SUBJECT_SCENES[$subjectSlug] ?? [];

        // 拼出「小学数学」这类学段 + 学科长尾词（搜索流量主要来自这里）
        $fullName = ($parentName !== null && $parentName !== '') ? $parentName . $name : $name;

        if ($scenes === []) {
            return "{$fullName}课堂工具合集，{$count}，覆盖日常备课、课堂演示与练习讲评。"
                . '全部免安装、断网可用，浏览器打开即可投屏使用。';
        }

        $sceneText = implode('、', array_slice($scenes, 0, 4));

        return "{$fullName}课堂工具合集，{$count}，覆盖 {$sceneText} 等常见场景。"
            . '全部免安装、断网可用，浏览器打开即可投屏使用；'
            . '拿不准用哪个，可以先挑一个试试，或回到所属学段浏览全部工具。';
    }
}
