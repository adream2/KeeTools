# 变更日志

本文件记录**项目级**变更（框架 / 前台 / 后台 / 工程规范）。
单个工具自身的变更记录在各工具目录的 `CHANGELOG.md` 中。

格式参考 [Keep a Changelog](https://keepachangelog.com/zh-CN/1.1.0/)，版本号遵循语义化版本。

---

## [未发布]

### 修复

- **批次 2 工具用户反馈修复轮（2026-09-19）**：收到 5 条反馈，逐条定位并修复；4 个工具版本升级
  （`clock-learning` 1.1.1 / `idiom-dict` 1.1.0 / `stroke-order` 1.1.0 / `lens-imaging` 1.0.1），
  逐工具明细见各工具 `CHANGELOG.md`
  - **时钟（clock-learning）**：反馈「只有时针分针、没有秒针，不能自由转动指针判断时间」
    → ① 钟面补齐**秒针**（红色细针）+ 设置开关「显示秒针」，说明文案同步为「短针是时针，长针是分针，细针是秒针」；
    ② 新增第三模式「**自由拨针**」——三针均可拖动、不吸附刻度、不判题，面板按钮变为「读数」实时报出指针所指时间，
    适合课上自由讲解演示；拖动的手势识别改为三针就近判定。
    后续反馈「自由拖动秒针满一周分针不变、时间归零重计，分针与时针同样问题」
    → ③ 原先按指针**绝对角度取模**赋值，绕满一圈只会归零；已改为**累计指针真正转过的角度**（跨 12 点不清零）并据此进位：
    秒针满 60 秒 → 分针进 1 分、分针满 60 分 → 时针进 1 时，反向拖动按借位回退，起手也不再跳针
  - **成语（idiom-dict）**：反馈「词库太少、设置里没有导入导出、缺少小初高词库」
    → ① 词库 597 → **645 条**（小学 245 / 初中 254 / 高中 146）并新增**学段筛选条**（全部 / 小学 / 初中 / 高中）；
    ② 设置抽屉内新增**词库导入 / 导出**（导出带 BOM 的 CSV，Excel 直开；导入支持 JSON/CSV 多种形态，
    可与内置合并或整体替换）；③ 接龙校验与首字索引统一改走合并后索引，导入的成语也参与接龙
  - **笔顺（stroke-order）**：反馈「没有动画，只能显示第几笔」
    → ① 内联 **hanzi-writer-data 真实笔迹中位线数据**（210 字全覆盖），实现**逐笔书写动画**：
    沿书写轨迹描红写出 + 笔尖实时游走，已写留红、未写浅灰，右侧笔画面板同步高亮；
    ② 顺带修正 3 处笔画数错误（飞 2→3 画、北 4→5 画、春 8→9 画）；
    ③ 数据来源与许可证（Arphic Public License）已在帮助弹窗与设置抽屉注明并登记 `THIRD-PARTY-LICENSES.md`
  - **凸透镜（lens-imaging）**：反馈「按钮与成像结果关联有 bug」
    → 根因是「放大率」被单独存了一份状态，与物距 / 焦距算出的实际值可能不一致，
    且按钮切换后结论带要等下一次操作才刷新；已改用 **`u / f` 实际比值作为唯一判据**、
    去掉冗余状态，并让焦距切换 / 典型位置 / 光屏移动后立即刷新结论带与光屏提示
  - **配套**：4 个工具均通过 `node --check` + 无头 Chrome `file://` 渲染抽检（截图逐一核对）；
    笔顺动画另用 DOM 事件驱动的 mid-flight 探针验证（`stroke-dashoffset` 处于扫描中、笔尖 `is-live`）；
    扫描入库 `scanned 26 / updated 26 / failed 0 / warnings 0`

### 新增

- **批次 3 交付：理科实验与演示（2026-09-19，7 个新工具，工具总数 26 → 33）**
  物理 4 / 化学 1 / 生物 1 / 数学 1；全部 `v1.0.0`，零外部依赖、单文件离线可用；
  六项门禁全绿 + 运行时抽检 33/33 通过 + 扫描入库 `failed 0 / warnings 0`；内置数据均经数值自证
  - **`pythagorean` 勾股定理演示**（fixed，数学）：三边正方形面积可视化；勾股数预设下「拼合动画」——两直角边正方形的格子逐格飞进斜边正方形，一格不差
  - **`chem-balancer` 化学方程式配平器**（fixed，化学）：内置 70 条初中方程式按 5 类反应筛选，填系数练习 + 检查 + 逐个提示 + 揭示答案；嵌套括号化学式解析器 + 元素守恒核对表；系数经 Python 求解器自证平衡且为最小整数比
  - **`microscope-sim` 显微镜模拟**（experiment，生物）：视野拖动（倒像）、粗/细准焦（高倍镜粗准焦警告）、物镜 10×/40× 转换器、目镜 5/10/16×、反光镜亮度；3 种玻片标本（洋葱表皮/口腔上皮/叶下表皮含气孔）；SVG 模糊滤镜实现失焦；5 步观察流程自动打勾
  - **`lever-balance` 杠杆平衡**（experiment，物理）：点刻度挂 0.5/1/2N 砝码，阻尼弹簧倾斜动画 + 砝码随时保持竖直；力矩明细与 F₁L₁=F₂L₂ 判定；出题挑战模式
  - **`light-reflection` 光的反射与折射**（experiment，物理）：拖动/滑杆调入射角，反射定律 + 折射定律实时光路；4 种介质组合（含 水→空气、玻璃→空气 全反射与临界角）；菲涅耳公式估算反射/折射光强占比；自动扫描演示
  - **`buoyancy-lab` 浮力探究**（experiment，物理）：体积/密度/液体可调，浮沉判定（漂浮悬浮下沉）+ 弹簧浮动动画 + 可拖拽；G / F浮 / N 力箭头（等长即等大）；阿基米德 F浮=G排 与测力计视重读数
  - **`pulley-lab` 滑轮组**（experiment，物理）：定滑轮/动滑轮/滑轮组 n=1/2/3 绳路动画（s = n·h 实测）；F=(G+G动)/n、W有用/W总/机械效率 η=G/(G+G动)；「数绳子」逐段高亮承重绳段
  - **配套**：`check_tools_runtime.py` 补 7 个工具的渲染标记；6 个工具各配「交互态探针」注入点击后截图核对动画（拼合完成态/杠杆平衡态/滑轮拉动后/漂浮态/高倍失焦态/配平展示态）

- **批次 2 交付：P1 学科覆盖（2026-09-19，8 个新工具，工具总数 18 → 26）**
  语文 2 / 数学 3 / 英语 2 / 通用 1；全部 `v1.0.0`，经 `new_tool.py` 脚手架生成，零外部依赖、单文件离线可用
  - **`pick-lottery` 抽签 / 抽题器**（shell，通用）：导入题目或任务清单逐个抽取 + 已抽标记 + 重置，
    支持加权抽取与「抽过的不再出现」；按序号一键批量生成「第 {n} 题」；结果导出 CSV（带 BOM）
  - **`stroke-order` 笔顺查询器**（fixed，语文，210 个常用字）：笔画数 + 笔顺名称序列
    （横竖撇点折…共 26 类笔画名）+ 田字格分步高亮当前笔画序号与名称；按笔画数筛选与单字查询；
    可调速自动播放。**只展示序号与名称，不绘制笔迹轨迹**（纯本地零依赖无笔迹数据源）
  - **`idiom-dict` 成语词典 / 接龙**（fixed，语文，230 条成语含释义与出处）：词典模式（搜索 +
    高频首字快捷筛选 + 卡片网格 + 详情面板）与接龙模式（首字校验、不重复校验、电脑自动接一环、
    最佳记录持久化）
  - **`geometry-builder` 几何图形构建器**（fixed，数学）：20×20 网格上点击加点、连线、闭合、
    拖点吸附、退一步 / 撤销 / 清空；实时算顶点数、边数、周长、面积（鞋带公式）；
    7 个图形模板（直角三角形 / 等腰 / 等边 / 正方形 / 长方形 / 平行四边形 / 梯形）+ 圆模式
  - **`clock-learning` 时钟认识**（fixed，数学）：内联 SVG 钟面，读数模式（拖针 → 填时/分）与
    摆针模式（给时间 → 摆指针）双向练习；四档难度（整点 / 半点 / 5 分 / 1 分）角度吸附；
    答对答错统计持久化
  - **`ipa-chart` 国际音标表**（fixed，英语，48 音标 = 20 元音 + 28 辅音）：按发音部位分组 +
    清浊徽标 + 例词，`speechSynthesis` 点读（能力检测 + 异步取音色，en-US 优先）；
    筛选（全部 / 元音 / 辅音 / 清辅音 / 浊辅音）；发音要点与英美音切换
  - **`word-flashcard` 单词卡片**（shell，英语，内置 75 词）：CSS 3D 翻面（`backface-visibility`
    降级兼容），认识 / 不认识分堆循环复习，TTS 朗读；`et-import` **三字段导入**（单词 / 音标 / 释义）
    与「按序号批量生成」；导出 CSV（带 BOM）
  - **`probability-lab` 概率模拟器**（fixed，数学）：抛硬币 / 掷骰子，单次动画 + 10/100/1000 批量；
    实时统计表（次数 / 频率 / 理论概率 / 偏差）+ 内联 SVG 频率收敛曲线（理论概率虚线 + 图例）+
    大数定律提示；系列上限 20 万次防内存膨胀
  - **工程**：`scripts/check_tools_runtime.py` 的 `TOOL_MARKERS` 补齐 8 条（DOM 标记断言）；
    8 个工具均通过 `node --check` + 无头 Chrome `file://` 渲染抽检（截图逐一核对）；
    扫描入库 `created 8 / failed 0 / warnings 0`；`/tools` 与语文·数学·英语分类页均可见

- **共享层：`et-import` 支持自定义字段模式（2026-09-19）**
  - `tools/_shared/ui/et-import.js` **加法式扩展**：新增 `fields: [{key, label, keys}]` 选项，
    用于导入非「姓名 / 学号 / 权重」结构的表（如单词卡片的 单词 / 音标 / 释义）；
    列名按 `keys` 多别名匹配，表头识别、CSV/TSV/JSON 解析与结果预览一并适配
  - **未传 `fields` 时行为与旧版完全一致**（`random-name-classic` / `wheel-spinner` /
    `scoreboard` / `random-grouping` / `dictation-helper` 等不受影响）；已同步至全部工具

- **电路工具补「典型电路图库」（2026-09-19）**：`circuit-builder` 1.1.0 → **1.2.0**
  - 新增**典型电路图库**（工具栏按钮 / 快捷键 <kbd>G</kbd>）：**12 个课本常见电路**的示意图缩略图——
    串联一灯、串联两灯带开关、两灯并联、并联·支路开关、并联·干路开关、电流表测电流、电压表测灯泡电压、
    串联两灯·测其中一灯、伏安法测电阻、滑动变阻器调光、电动机电路、短路演示（危险）；点图即载入，每个附一句要点
  - 图库顶部附**电路符号对照表**（导线 / 电源 / 开关 / 灯泡 / 定值电阻 / 滑动变阻器 / 电流表 / 电压表 / 电动机）
  - 示意图由**同一套电路数据实时生成 SVG**（零外部资源），连接点、符号朝向、电表「并联跨接」画法与画布一致
  - 12 个示例**逐个跑求解器自证**（无头 Chrome 注入探针）：并联总电流 0.509A；
    **支路开关只担 0.252A、干路开关担满 0.509A**；电压表跨接读数 2.65V 且自身 0A；串联两灯各 1.41V；
    伏安法 0.266A / 2.66V → R = 10Ω；短路演示给警告且总电流 2.07A、灯泡仅 0.031A
  - 顺带修复：**判定遗漏电动机**（只有电动机时误报「还没有接入用电器」）；补上电阻电路 / 电动机电路的结论文案

- **首批工具用户反馈修复轮（2026-09-18）**：收到 9 条反馈，逐条定位并修复；9 个工具版本升级
  （`alphabet-chart` 1.1.0 / `countdown-timer` 1.4.0 / `tianzige-writer` 1.1.0 / `fraction-visual` 1.1.0 /
  `math-drill` 1.1.0 / `function-grapher` 1.1.0 / `periodic-table` 1.1.0 / `poem-cards` 1.1.0 /
  `circuit-builder` 1.1.0），逐工具明细见各工具 `CHANGELOG.md`
  - **发音（alphabet-chart）**：读字母被语音引擎读成冠词弱读 → 改为「字母 + 句点 + 例词」的教材读法；
    新增点读设置抽屉（语速 / 朗读内容 / **具体语音选择**，按 Natural·Neural·Google 优先排序）；
    音标体系可切英美（o、r、z 三字母英美不同，详情同列两种）；顶栏显示当前生效语音名
  - **电路（circuit-builder）**：① 示例「两灯并联」其实是串联——原因是示例把电源串在分支里、
    电流只有一条路径（**求解器判定正确，示例图画错**），已重画并在帮助里写清并联接法要点；
    ② 补齐实验元器件：电动机 / 滑动变阻器 / 电流表 / 电压表（含内阻、读数、短路判定纳入）；
    ③ 开关改为绿（通）/ 红（断）刀杆 + 触点 + 断口虚线 + 「通 / 断」标注，元件标签改为屏幕坐标系绘制
  - **倒计时（countdown-timer）**：新增**正计时（秒表）模式**与到点提醒（设时长 → 响铃 + 变红），
    进度环双模式适配
  - **分数（fraction-visual）**：色条补上拖动视觉反馈——把手显示当前分数、拖动高亮、空格悬停提示
  - **函数（function-grapher）**：修复 `sin x` / `sqrt x` 空格写法报错（tokenizer 不再压缩空白），
    并修掉 `log2(x)` 被静默解析成 `log(2)·x` 的错值；函数库扩到 40+（双曲、cot/sec/csc、cbrt、log2、
    frac/step/relu/clamp、gauss/sinc/square/triangle/saw），新增 tau/phi 与 `**` 乘方，示例函数 9 → 20
  - **口算（math-drill）**：新增**在线答题模式**（逐题作答、回车跳题、批改判分、错题给答案、
    用时统计、重做、只练错题）；修复算式溢出格子（按实际列宽自适应字号，打印固定 15px）
  - **元素周期表（periodic-table）**：点击元素弹出详细介绍——**可旋转 3D 原子模型**（SVG + CSS 3D，
    零外部库）+ 英文名 / 中子数 / 电子排布式（Aufbau）+ **逐元素性质与用途**（常见元素逐条撰写，
    其余按类别说明）；新增「点击弹详情」开关与底部入口
  - **古诗词（poem-cards）**：库容 80 → **121 首**（补齐初中 30 首与高中 11 首，新增「高中」学段与
    「先秦 / 汉魏」朝代筛）；新增**作者与注释**弹窗（作者简介覆盖 60 余位作者 + 创作背景 +
    词语注释 + 主旨赏析，62 个重点篇目配齐）
  - **字帖（tianzige-writer）**：范字过小的根因是 `.tz-cell__char { font-size: 76% }` 相对页面字号计算
    （实际约 12px），改用 **SVG 范字**承载字号 → 随格子等比缩放，屏幕与 A4 打印都符合字帖标准；
    新增「范字大小」三档设置
  - **配套**：`scripts/check_tools_runtime.py` 增强——注入 `window.onerror` 把未捕获异常写进
    `document.title` 再校验（Chrome 无头默认不把控制台报错写进 stderr，此前会漏检
    「脚本抛错但 DOM 仍有内容」的情况；本轮即由此抓到一次真实报错）

- **P4 规模化（2026-09-18，进行中）**
  - **工具生产流水线**
    - `tools/_template/`：新工具骨架模板（`index.html.tpl` / `manifest.json.tpl` /
      `CHANGELOG.md.tpl` + README），`@@NAME@@` 占位符避开 CSS 百分号与 JS 花括号
    - `scripts/new_tool.py` 重写：从 `_template/` 渲染 → 自动登记 `shared.manifest.json`
      → 自动跑 `sync_shared.py` 内联共享片段 → 跑 `check_manifest.py` / `check_no_external.py`
      自检 + 体积守卫；新增 `--family` / `--accent` / `--yes`（非交互批量生产）
    - 骨架自带 `et-chrome` 外壳、ET:INLINE 标记、翻页笔主操作、空格键、localStorage 持久化
    - **单工具体积守卫**：`check_manifest.py` 超 `TOOL_MAX_KB`（默认 500KB）告警，
      超限须在 `docs/工具例外清单.md` 登记（已登记 `pinyin-chart` 内嵌音频场景）
  - **SEO（P4 §三）**
    - `/sitemap.xml` + `/robots.txt` 动态端点（`SeoController`）：域名自适应；归档
      `/tool/*/v/`、`/search`、`/admin`、`/netdisk/`、`/download/` 一律不进索引
    - 站点布局补齐 SEO 头：`robots` / `canonical` / `keywords` / Open Graph / Twitter Card
    - 详情页关键词由 学科 + 标签 + 学段 自动拼出；分类页新增**导语正文**
      （`CategoryCopy`，学段 + 学科长尾词，如「小学数学课堂工具合集」；可用
      `category_intro.{slug}` 在 `site_config` 覆盖）；首页 SEO 标题与关键词可在后台覆盖
    - `/search` 结果页 `noindex,follow`；`/tools` 为稳定列表页正常收录
  - **数据驱动看板（P4 §四）**
    - 仪表盘新增「工具数增长（近 12 个月）」柱状图与「学科分布」条形图 +
      规模目标进度（≥30 工具 / ≥5 学科有工具）
    - 统计看板新增**零使用工具清单**（近 30 天无任何事件的已上架工具），
      支持一键「重做元数据」或「下架」（新增 `POST /admin/tools/{id}/published`，
      只改站点索引，不动 `tools/` 目录与 manifest）
  - **P4 收尾（机制部分全部就位）**
    - `hello-keetools`（P0 期空壳演示）改造为 **`tool-template`「工具模板示例」v1.0.0**：
      完整 `et-chrome` 外壳 + 设置抽屉 + 视图切换 + 「开发四步」活文档；
      `stats_enabled: false`（示例不污染统计口径）
    - **工具定制需求入口**：`custom_tool_url` / `custom_tool_note`（后台「赞助」Tab 配置），
      详情页侧栏卡片；未配置时页面零痕迹，URL 过 `Security::safeExternalUrl` 白名单
      （新增 `SiteOps::customToolEntry()`）
    - **体积审计** `scripts/asset_report.py`：`public/` 按目录与类型汇总 + 各工具 HTML
      体积排行（> 500KB 自动提示登记例外清单）
    - **规范补充** `docs/工具开发规范.md` §6.4.1：工具 HTML 中**禁止**出现
      「完整的 `ET:INLINE` 标记文本」与「script 结束标签字面量」——前者会被 `sync_shared.py`
      误注入、后者会提前闭合外层 `<script>`，两者都属"门禁全绿但页面已损坏"的静默故障
    - **产出批次计划** `tasks/P4-工具批次计划.md`：依据 `KeeTools_工具清单.md` 拆 6 批
      （每批 7–8 个，共 46 个新工具），含 id / type / 学段 / 学科 / 要点与每批 DoD
    - **三项待决策定稿**：不引入 CI（单人开发，CI 覆盖不到部署自检）；工具总数软上限 100；
      不做"用户提交工具"入口（V1 无用户系统，用定制需求入口承接）

  - **首批新工具 5 个**（全部单文件、离线可用、大屏优先、各带独立 accent）
    - `random-grouping` v1.0.0 — 随机分组：按组数 / 按每组人数两种分法，Fisher-Yates 洗牌，
      余数分散保证人数均衡，组内可选按姓名排序（Intl.Collator）
    - `math-drill` v1.0.0 — 口算题生成器：加减乘除可多选，减法不为负、除数不为 0、
      可选是否进位借位与整除，答案显隐切换，打印 / 另存 PDF
    - `tianzige-writer` v1.0.0 — 田字格字帖：田 / 米 / 空白格，描红 + 自写遍数可调，
      内置 120 常用字，楷体 / 黑体（系统字体栈），打印友好
    - `dictation-helper` v1.0.0 — 听写助手：词表自动报词、隐藏文字、朗读遍数 / 间隔 /
      语速可调；语音能力检测（不支持时降级为「只显示词语」）
    - `alphabet-chart` v1.0.0 — 英语字母表（fixed 型）：26 字母大小写 / 音标 / 例词，
      元音高亮，卡片与列表双视图，点读发音 + 大小写书写提示

  - **P4 批次 1：7 个新工具**（2026-09-18；工具总数 11 → 18，六项门禁全绿、
    扫描入库 0 失败 0 警告、前台 `/tools` 与各学科分类页均已可见）
    - `times-table` v1.0.0 — 乘法口诀表：9×9 方阵点格揭晓 / 遮住、随机抽考、「抽一个」点题、
      口诀朗读（按「小数在前、积小于 10 加『得』」的传统读法，`speechSynthesis` 能力检测 + 静默降级 + 三档语速）、
      行 / 列高亮讲专题、1–5 与 6–9 范围切换、状态持久化
    - `fraction-visual` v1.0.0 — 分数可视化：SVG 饼图 + 等分色条（拖动 / 触屏改分子）、
      自动约分并说明同除以几、对比模式（分数 B + 数轴 + 通分过程 + 大小判定）、常用分数快捷位
    - `poem-cards` v1.0.0 — 古诗词卡片：内置 **80 首**必背篇目（按小学低 / 中 / 高年级与初中标注），
      全显 / 逐句 / 隐藏三种模式（课堂抽背）、学段与朝代筛选 + 搜索、收藏与只看收藏、朗读、大字模式
    - `function-grapher` v1.0.0 — 函数图像绘制器：自研解析式编译器（递归下降编译为闭包，
      支持省略乘号 `2x`、`^`、`|x|`、`√`、三角 / 对数 / 指数等函数与 `pi`、`e`），
      Canvas 自适应网格绘图 + 拖动平移 + 光标缩放 + 坐标追踪、交点与最高 / 最低点自动标注
      （二分法求根 + 三点抛物线插值）、数值表、PNG 导出、角度制开关
    - `periodic-table` v1.0.0 — 元素周期表：118 种元素（序数 / 符号 / 中文名 / 相对原子质量 / 类别 /
      周期 / 族 / 电子层排布），CSS Grid 标准布局含镧系 · 锕系两行、点击看详情、
      周期与族高亮、类别筛选 + 搜索、10 类配色图例；电子层排布按构造原理计算并对
      Cr、Cu、Nb、Mo、Ru、Rh、Pd、Ag、La、Ce、Gd、Pt、Au 与锕系若干元素按实际排布修正
    - `circuit-builder` v1.0.0 — 电路搭建模拟：画布虚线格上拖拽电池 / 开关 / 灯泡 / 电阻 / 导线，
      **节点法列方程 + 列主元高斯消元**实时求解各支路电流（电源内阻 0.5Ω、导线 0.05Ω、
      节点微导纳防奇异），灯泡亮度按实际功率 P = I²R 变化、电流方向与大小以流动虚线 +
      箭头表示；**自动判定串联 / 并联**（分别断开一只灯泡看另一只是否仍亮，与课堂做法一致）、
      短路（导线直连电源两端的存在性判定）与断路告警、4 个现成电路一键载入
    - `lens-imaging` v1.0.0 — 凸透镜成像模拟：按 1/u + 1/v = 1/f 实时绘制三条特殊光线
      （平行主光轴 / 过光心 / 过焦点），像的位置 · 正倒 · 大小 · 虚实（虚像用虚线箭头 +
      折射光线反向延长线），可拖动光屏且靠近像位置自动吸附并显示「清晰 / 模糊」光斑，
      五个典型位置预设（照相机 / 等大 / 投影仪 / 不成像 / 放大镜）与生活应用对应
    - **工程配套**：新增 `scripts/check_tools_runtime.py`（工具运行时抽检：抽 `<script>` 块跑
      `node --check` + 无头 Chrome/Edge 打开 `file://` 抓 DOM、截图与控制台报错；
      **非门禁**，无 Node / 浏览器时优雅跳过），补齐六项静态门禁抓不到的
      「门禁全绿但页面已损坏」盲区；已固化为每批交付前的必做步骤（批次计划 §2.2）

- **P3 离线包（2026-09-18）**
  - **打包服务** `src/services/PackageBuilder.php`：异步任务模式（`package_tasks` 表），
    CLI（`scripts/build_package.php`，供 cron）与后台轮询端点惰性消费双端共用同一实现；
    ZipArchive 逐文件 addFile 分批落盘（禁 addFromString 大文件进内存）、
    体积守卫 `PACKAGE_MAX_SIZE_MB`（默认 10MB，超限中止提示拆包）、
    DB 状态 + `var/tmp/package.lock` 文件锁双保险（同时仅 1 个任务）、
    临时目录 `var/tmp/package-{taskId}/` 成功/失败/超时均清理、产物记录 sha256 台账（`packages` 表）
  - **离线导航门户** `src/services/PortalRenderer.php`：与在线版共用同一 CSS 层与类名
    （内联 site.bundle.css + 门户专属 `assets-src/css/portal.css` + 图标 sprite），
    单文件 `index.html`，`file://` 双击可用、零网络请求；数据内嵌 + `tools-index.json` 附赠；
    本地搜索 + 学段/学科筛选 + 版本号展示；门户 JS 为 ES5（老教室浏览器兼容）；
    站点外链全部带 UTM `?from=offline-pkg`（品牌回流主力触点）
  - **后台打包页**（仅 admin，editor 403）：工具多选 + 实时包体预估 + 进度条轮询 +
    历史产物（下载 / 删除 / 按原配置重新生成）；打包完成提示到「网盘管理」回填链接（联动 P1）
  - **分发辅助**：产物旁自动生成网盘上传清单（包名/体积/SHA256/建议网盘/提取码占位，
    不随包分发）；打包前校验工具 HTML 内 ET-META 版本与站点记录一致，避免发旧包
  - 包结构：`{slug}-{version}/`（index.html + tools-index.json + tools/*.html + 使用说明.txt + 使用说明.html + 关于KeeTools.txt）
  - **P3 补全（三项待决策落地 + PWA 定稿）**：
    - 门户二维码快照：复用站点设置 `community_qr_image`，打包时转 data URI 内嵌门户页脚
      （file:// 零外部请求）；支持 http 拉取（5 秒超时）/ 站内路径 / data URI 直传，
      图片格式嗅探（PNG/JPEG/WebP）+ 512KB 上限，任何失败优雅降级为无码
    - 增量包辅助：打包页历史产物行「勾选变更（N）」——相对该包有更新（updated_at 变化）
      或新增的工具一键勾选 + 预填 `{slug}-update` 包名
    - 使用说明 HTML（打印友好）：工具清单表格 + Ctrl+P 一步存 PDF（零依赖替代真 PDF），
      与二维码联动
    - PWA 定稿：只做工具级（P2 `et-chrome` blob manifest 已实现），不做站点级

- **P2 标杆工具（2026-09-18，5 个工具 + 共享基建）**
  - **`_shared/` 基建沉淀**：
    - `ui/et-chrome.js` + `ui/et-chrome.css` — 工具通用外壳（顶栏 / 状态胶囊 / 深浅色主题 /
      全屏 / WebAudio 音效开关 / 帮助弹窗 / toast / L1 首次运行提示 / L2 页脚回流 +
      postMessage 站点发现协议 `et-hello`/`et-origin`，收不到回复时优雅降级为纯文本）
    - `ui/et-import.js` + `ui/et-import.css` — **通用数据导入组件**：粘贴文本 / 文件
      （CSV·TXT·JSON 拖放）、RFC4180 引号 CSV 解析、UTF-8/UTF-16/GBK(GB18030) 编码自动识别、
      表头与列自动识别（姓名/学号/权重）、去重、空行处理、前 10 行预览、逐行报错定位
    - `logic/random-pick.js` — 加权随机 / 候选池 / 洗牌 / 多抽
    - `logic/countdown.js` — 漂移校正倒计时引擎（绝对时钟 + 100ms 粒度）+ 格式化
    - `logic/scoreboard.js` — 多队计分 / 历史 / 撤销 / 8 色调色板
    - `icons/icons.svg` — 工具图标 symbol 集（Lucide 子集约 40 个，ISC，独立于网站 sprite）
  - **工具 5 个**（全部单文件、`file://` 双击可用、深浅色主题、全屏、空格键快捷操作）：
    - `random-name-classic` v1.0.0 — 随机点名器：大屏舞台、点名定格动画 + 彩带特效、
      不重复点名 / 已点标记 / 加权抽取 / 历史（复用 et-import + random-pick）
    - `wheel-spinner` v1.0.0 — 课堂大转盘：SVG 扇区按权重分配、rAF 缓动旋转 +
      指针扫过扇区边界 tick 音效、中奖放大展示、抽中自动移除（可撤销）
    - `countdown-timer` v1.0.0 — 课堂倒计时：SVG 进度环、8 档预设 + 自定义时分秒、
      运行中 ±30 秒、最后 30 秒变色 / 10 秒滴答、「时间到」全屏提醒
    - `pinyin-chart` v1.0.0 — 汉语拼音表（fixed 型渲染器模式验证）：63 项内置数据、
      卡片 / 列表双视图、点击朗读（SpeechSynthesis 优雅降级）、分节连读、实时检索、
      数据内联 JSON 可整体替换
    - `scoreboard` v1.0.0 — 小组计分板：2–8 队、±1/±5、计分历史弹窗、一键撤销、
      领先队伍皇冠高亮、队伍名批量导入（复用 et-import + scoreboard）
  - **规范修订**（浏览器基准，见 `docs/工具开发规范.md` §4.1）：工具侧由
    "Win7 + 老版浏览器 ES5" 修订为**现代浏览器优先**（Chrome/Edge 90+、Safari 14+），
    浏览器内置能力必须能力检测 + 优雅降级；`AGENTS.md` §6.3 同步更新
  - 浏览器实测：5 工具全部通过（HTTP 与 `file://` 双协议、导入/解析/交互/动画全链路、
    DevTools 无页面错误、Network 零外部请求）
- **P2 工具体验修订 v1.1.0（2026-09-18，用户反馈 9 项全量落地）**
  - **去启动弹窗**：取消 L1 首次运行提示（工具侧 `et-tip` 与下载注入 `BrandInjector` toast 均移除），
    品牌回流仅保留低调页脚（规范 §八改为两层设计）
  - **设置抽屉**（edupick 交互）：数据型工具右上角 ⚙ 抽屉集中收纳导入/导出/选项/记录，
    `et-chrome` 新增 `opt.settings` API 与抽屉组件
  - **按学号快速生成**：random-name / wheel / scoreboard 均支持起止号 + `{n}` 模板一键生成，免手打名单
  - **离线名单**：工具同目录可选 `roster.js`（`window.ET_ROSTER`，`file://` 生效）或 `roster.txt`
    （仅 http），打开即自动加载、优先于本机数据（PPT 直开免设置）
  - **iframe 全屏委托**：工具全屏按钮 postMessage 给宿主统一 fullscreen（`et-fullscreen-toggle`
    / `et-fullscreen-change`），`site.js` 实现桥接 + `et-hello`→`et-origin` 应答（页脚官网链接生效），
    消除详情页"全屏体验"与工具内全屏的双层嵌套
  - **PWA 可封装**：`et-chrome` 自动注入 blob manifest + theme-color（不支持的环境静默）
  - **模块化离线统计**：新增 `ui/et-stats.js`——独立打开且联网时 sendBeacon 上报
    `use_offline`（text/plain 免预检），端点未配置零请求；整块可删、删后完全不联网；
    `StatsService::EVENTS` 与 `init_db` CHECK 白名单扩至 6 事件（旧表自动重建）；
    `BrandInjector` 改为注入 `ET_SITE_ORIGIN`（下载单文件获得页脚链接与统计端点）
  - **拼音朗读修复**：改为朗读例字汉字（中文 TTS 最准）+ 优选本地中文语音包（zh-CN localService）+
    语速放缓至 0.65，无中文语音包时明确提示
  - **提示音量提升**：WebAudio 增益按教室大屏调校（MASTER_GAIN 0.5，单项峰值 ×2.5）
- **P2 激光笔适配 v1.2.0（2026-09-18）**
  - `et-chrome` 新增 `opt.primary` / `opt.secondary` 约定：翻页笔上下页键
    （PageDown / PageUp，HID 翻页键）即工具主 / 副操作键，输入框聚焦与弹窗打开时不响应
  - 接入工具：random-name-classic（点名）、wheel-spinner（旋转）、countdown-timer
    （下页开始/暂停 · 上页重置）、pinyin-chart（下页连读下一节 · 上页上一节，领读不碰鼠标）
- **P2 精致化 v1.3.0（2026-09-18，用户反馈三项）**
  - **拼音标准读音**：新增构建脚本 `scripts/pinyin_gen_audio.py`——用 Windows 中文标准语音
    （Huihui/Kangkang）合成 58 个音节 8kHz WAV，Python 标准库裁剪静音 + 峰值归一 + base64
    内嵌（约 460KB 入库），运行时零依赖完全离线；`speak()` 优先播内嵌音频，缺项回落 TTS。
    根治 TTS 读拼音变英语音的问题
  - **双层全屏根治**：iframe 内工具不再渲染自身全屏按钮（宿主「全屏体验」是唯一全屏入口）；
    站点 CSS 预览区 `:fullscreen` 时隐藏 preview-bar/preview-tip，全屏即纯工具本体
  - **打开即用**：random-name-classic 无离线名单/本机数据时自动载入默认学号名单（1–40 号），
    打开即可点名；设置只用于改参数
  - **品质升级**：`et-chrome` 全局极光呼吸背景（accent 渐变 + 16s 动画，老内核友好）；
    点名结果 / 倒计时数字改 fg→主题色渐变大字 + 辉光；点名舞台玻璃化

- **P1 转化（2026-09-18，全部 8 个模块）**
  - **统计体系**：`services/StatsService.php`（append-only JSONL 缓冲 + 批量落库 + 限流 + use_online 去重）、
    `POST /api/track` 端点、`scripts/flush_stats.php`（cron 每分钟落库，缓冲超 512KB 惰性落库、看板渲染前自动 flush）、
    `services/RateLimiter.php`（文件计数限流器）。事件白名单扩至 5 个：
    view / use_online / netdisk_click / download_direct / sponsor_click；IP 只存日盐哈希
  - **在线使用页** `/tool/{id}/use`：同源 iframe 输出工具 HTML，服务端向 `</body>` 前注入
    ES5 统计 IIFE（`services/StatInjector.php`，与直记通道去重防双计）；
    多文件工具降级为引导页（use-fallback）
  - **详情页改造**（转化页）：懒加载在线预览（点击加载 iframe，无 JS 时 noscript 直出）、
    主按钮「下载合集包」（首个活跃网盘，缺省为「整理中」禁用态）、次级文字链「仅下载本工具」、
    OG/meta description/JSON-LD 结构化数据
  - **网盘中间页** `/netdisk/{id}?type=`：提取码 + 10 秒倒计时（noscript 直达）+ 复制按钮 +
    失效反馈（check_status=reported + 日志，看板提示）+ 其他网盘渠道（失效标黄）+ 引流区
  - **单文件直接下载** `GET /download/{id}`：`DOWNLOAD_DIRECT_ENABLED` 总开关、10 次/分钟限流、
    服务端强制注入品牌回流（L1 toast + L2 页脚，`services/BrandInjector.php`）、
    download_direct 统计（允许丢失，不做补偿）
  - **后台网盘管理** `/admin/netdisks`（仅 admin）：CRUD / 启停 / 单条与批量探活
    （`services/NetdiskChecker.php`，HEAD→GET、404/410 判失效）
  - **后台统计看板** `/admin/stats`：总览卡 / 网盘点击排行 / 工具 Top10 / 近 30 天趋势 /
    浏览→网盘转化率 / 失效反馈待处理提示
  - **站点运营模块**（`docs/站点运营模块设计.md` 全量落地）：
    `services/SiteOps.php` 统一访问层（关闭零痕迹 / URL 白名单集中过滤）、
    广告位 6 插槽（设备 CSS 类区分 + 生效期日期比较 + 「广告」标签）、
    公告（通栏 + 公告中心弹层 + 内容指纹关闭记忆）、页脚设置（版权占位符 / 可点击备案行 / 声明）、
    友链（首页 / 内页投放面分离，每面最多 20 条，非法 URL 前台零痕迹）、
    赞助（`/sponsor` 页 + 收款码双卡 + 「我扫了这码」匿名上报 + 鸣谢列表 + 页脚/首页 CTA 入口）、
    `Security::safeExternalUrl()` 白名单（拒绝 javascript:/data:/file:/协议相对 //）
  - **后台设置页拆 Tab**：/admin/settings（基本）+ /footer /links /ads /announce /sponsor，
    分区保存互不影响（验收通过：保存页脚后 ad_slots 一字不变）；原始 HTML 仅 admin 可写（role 中间件）
  - 数据库扩表：`ad_slots` / `announcements` / `friend_links` / `sponsor_thanks`（含幂等迁移：
    stats 表 CHECK 白名单扩展时自动重建）；图标 sprite 扩至 60 个

### 修复

- `Security::safePath()` 引用了未定义的 `isInside()`（P0 遗留，使用页/下载端点首次触发）
- `Config::get()`：.env 值为空字符串时视为「未定义」回落 site_config，
  使 `DOWNLOAD_DIRECT_ENABLED=`（留空）成为「交由后台管理」的约定写法
- `ToolRepository::CARD_FIELDS` 缺 `entry` 列，使用页读取入口文件报 Undefined key

### 新增

- 数据库初始化：`scripts/init_db.php`（PHP，复用框架 `Database` 类）
  - 双库建表：`storage/app.db`（categories / tools / tool_category / tool_netdisks / admins / site_config / login_attempts / tool_overrides）+ `storage/stats.db`（stats，物理隔离写锁）
  - 分类种子数据：学段一级（小学 / 初中 / 高中）+ 学科二级共 37 条，slug 组合形如 `junior-physics`
  - 幂等可重跑；`--check` 只读校验表就绪；`--force` 删库重建
  - SQL 保持标准写法（参数化、无 `INSERT OR REPLACE`），预留 MySQL 迁移预案
- `Database::runSqlString()` 多语句执行方法（`runSqlFile()` 改为复用）
- 建立标准 VibeCoding 工程文件：`AGENTS.md`、`README.md`、`.gitignore`、`.gitattributes`、`.editorconfig`、`.env.example`、`CHANGELOG.md`、`THIRD-PARTY-LICENSES.md`
- 建立 `docs/` 规范文档集（需求文档 / 架构说明 / 工具开发规范 / manifest 规范 / 编码规范 / 安全规范 / 图标与许可证 / 工具例外清单）
- 建立 `tasks/` 分阶段任务计划表（P0-P5 + 导航 README）
- 建立 `scripts/` Python 工程脚本：
  - `sync_shared.py` — `_shared/` 片段幂等内联（含路径穿越防护、`--check` 模式）
  - `check_manifest.py` — manifest 字段 + `ET-META` 一致性校验
  - `check_no_external.py` — 零外部依赖检查（豁免 XML 命名空间）
  - `check_css_tokens.py` — CSS 字面量检查（强制设计令牌）
  - `build_css.py` — 四层 CSS 拼合产出 `public/assets/css/`（含 `--check` 模式）
  - `new_tool.py` — 新工具脚手架（生成后自动跑校验）
  - `icons/build_sprite.py` — 网站图标 sprite 子集化（离线源优先 SVG，其次 Iconify JSON）
  - `icons/prepare_source.py` — 从 Iconify 提取图标离线源（**唯一联网脚本**，`--dry-run` / `--force`）
- 建立目录骨架：`public/`、`src/`、`assets-src/`、`tools/_shared/`、`var/`、`storage/`、`packages/`
- 建立 `tools/_shared/` 共享逻辑源（`logic/et-util.js` / `icons/icons.svg` / `shared.manifest.json`）
- 新增示例工具 `tools/hello-keetools/`（v0.1.0），用于验证扫描入库、`ET-META` 一致性与同步链路
- 新增 `docs/站点运营模块设计.md`：广告位 / 公告 / 页脚设置 / 友链（首页·内页分离）/ 赞助的
  数据表、渲染位置、后台 Tab 与 URL 白名单安全约定（参考 edupick 旧版实现，实现排期 P1 §八）
- 新增 `.githooks/pre-commit`，一键跑**六项**校验（含 `build_css.py --check` 与 `build_sprite.py --check`；需 `git config core.hooksPath .githooks`）
- 网站图标链路打通：
  - `assets-src/icons/lucide.json` — Lucide **按需子集**离线源（46 图标 + 4 别名，约 14KB，构建全程不联网）
  - `public/assets/icons/sprite.svg` — 图标 sprite 产物（46 个 `<symbol>`），随 CSS 产物一并入库
  - 前台页头导航接入 `icon()` 辅助函数，输出 `<use href>` + `xlink:href` 双属性兼容老内核
  - `scripts/icons/build_sprite.py` 增加 **Iconify 别名回溯**（`home`→`house` 等）与
    `currentColor` / `none` 保护，并新增 `--check` 模式
- `asset()` 辅助函数新增 `$versioned` 参数，SVG sprite 走不带查询串的稳定地址

### 变更

- 需求文档升级至 v2.1：
  - 采纳决策 B — `tools/_shared/` 共享逻辑源 + `sync_shared.py` 同步机制
  - 单文件约束由"强制"改为"尽量单文件 + 例外清单"
  - 确认弃用 TailwindCSS，附决策留痕与兜底切换方案
  - 确认 Python 3 作为工程脚本语言
  - 补充环境变量规范（含无密码后台）
  - 补充设计令牌骨架（§7.5）
  - 补充临时产物隔离规则（§13.2）

---

## 说明

- `新增` — 新功能
- `变更` — 对既有功能的修改
- `修复` — bug 修复
- `移除` — 删除的功能
- `安全` — 安全相关
