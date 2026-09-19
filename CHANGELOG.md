# 变更日志

本文件记录**项目级**变更（框架 / 前台 / 后台 / 工程规范）。
单个工具自身的变更记录在各工具目录的 `CHANGELOG.md` 中。

格式参考 [Keep a Changelog](https://keepachangelog.com/zh-CN/1.1.0/)，版本号遵循语义化版本。

---

## [未发布]

### 定位与规划

- **第三方素材登记自动化（2026-09-19）**：唯一真源迁移至 **`assets-src/third-party.json`**——
  `/about` 页由新服务 `ThirdPartyNotices` 自动渲染（JSON 缺失/损坏时页面降级提示不 500），
  `THIRD-PARTY-LICENSES.md` 改为 `scripts/gen_licenses.py` 生成（`--check` 已入 CI）。
  新增素材只改 JSON 一处，跑脚本即同步两处展示，彻底消除三份手工维护（about.php 硬编码 /
  THIRD-PARTY-LICENSES.md / 图标与许可证清单）漂移。
- **「单文件」口径纠偏（2026-09-19，用户定稿）**：单文件是**尽可能达成的目标，不是硬性门槛**。
  大型工具（大素材 / 大数据无法内联）允许 `single_file: false` + `assets/` 相对引用，
  离线合集包整目录分发，「双击 index.html 即用」检验标准不变。
  已同步：README、AGENTS.md（定位语 + 技术栈表）、docs/工具开发规范.md §一、
  首页 Hero（「下载单文件」→「下载工具」）、docs/图标与许可证.md 合规清单（登记指向 JSON）。

- **产品定位定稿（2026-09-19，竞品调研后）**：主战场 = **离线**——单文件下载、U盘即插即用、
  教室断网可用；在线为应急使用。竞品调研结论：classtool.cn（38 工具）/ 酷课堂 / BeMoFun
  等同类站全部纯在线无离线能力，「学科可视化教具合集 × 离线单文件 × 可共建」全网无重合；
  巨头（希沃白板 / 班级优化大师）赛道（课件集成、多端互动、云同步）明确不打。
  落地：首页 Hero 改版（离线卖点置顶：U盘即插即用 / 断网可用 / 数据存本地 / 完全免费）。
- **批 7 工具计划启动**：补齐竞品验证过的高频刚需——评语生成器（已交付）、噪音计、
  早读检测、考试座位打印、班级激励游戏化等（后续批次逐个交付）。

### 工具

- **tool(report-comment): v1.0.0 评语生成器**：批 7 首发工具（工具总数 58）。
  单个生成（姓名 + 12 类表现特点标签勾选 + 亲切/正式双文风，内置约 110 条原创短语句库）
  + 全班批量（`姓名,特点1/特点2` 格式，特点可省略随机抽取，一键导出带 BOM 的 CSV）；
  含待改进项自动切换鼓励型期望句；生成后自动复制开关、最近 20 条历史；
  全部本地完成零上传；翻页笔/空格键主操作、深浅色主题、iframe 全屏委托。
  六项门禁 + 运行时渲染检查通过，自动上架验证 OK（/tools 列表已收录）。

### 修复

- **受众文案调整（2026-09-19，用户反馈）**：站点主要面向老师，但部分工具学生、家长也可用。
  全站「面向中小学老师的…」口径改为「面向中小学课堂 / 老师讲课、学生自学、家长辅导都用得上」：
  首页 Hero、关于页导语、网盘中间页赞助引导、首页赞助 CTA、离线包门户页与 README 文本
  （PackageBuilder 模板）。后台 `.env` / 站点设置里的 `SITE_DESCRIPTION` 为运营自配值，
  不在代码内强改。

- **公安联网备案徽标接入（2026-09-19，用户反馈）**：此前页脚只输出备案号纯文本，
  缺少公安联网备案要求的官方徽标。已从官方源（beian.gov.cn）离线化徽标 PNG
  → `public/assets/img/beian.png`（20×20，构建期一次性素材本地化，运行时零外链）；
  页脚备案号前渲染徽标；后台未配置 `footer_police_url` 时自动从备案号提取数字
  生成官方查询链接 `beian.mps.gov.cn/#/query/webSearch?code=<号>`（合规出链，
  `check_no_external` 已按署名链接先例豁免）。端到端验证：配置备案号 → 徽标+链接渲染；
  清除 → 零痕迹。

- **移除手动「扫描同步」，改为自动上架（2026-09-19，用户定稿）**：工具放入 tools/ 目录
  即自动上架，无需任何后台操作。实现：`ToolScanner::syncChanged()` 惰性增量同步，
  bootstrap 每次请求入口调用（仅 Web + 有库；CLI 不触发）——比对目录 manifest mtime
  与库内记录（N 次 stat + 1 条查询），无变化零写入；新目录入库上架、mtime 变化重扫、
  目录消失自动下架（保留行，目录回来即恢复）；异常只记日志绝不阻断渲染。
  移除：`/admin/tools/scan` 路由、`ToolAdminController::scan()`、整库 `scan()` 方法、
  工具列表与仪表盘扫描按钮；同步更新全部相关文案（首页空态 / 工具列表 / 仪表盘 /
  PackageBuilder 报错语）。端到端验证：touch manifest → 一次请求 → 库 mtime 自动更新
  + 日志落盘。

- **移除工具「编辑」页（2026-09-19，用户定稿）**：manifest 镜像字段（标题 / 描述 / 作者 /
  学段 / 学科 / 标签等）在工具开发时即已确定，manifest 是唯一真源，后台编辑再「回写
  manifest」属于冗余闭环。移除：`ToolAdminController::edit()/update()` 及表单辅助、
  `views/admin/tool-edit.php`、2 条 `/tools/{id}/edit|update` 路由、工具列表「编辑」按钮、
  统计页「重做元数据」按钮（改「看前台」）。后台工具管理只剩用户态职责：
  推荐 / 上架 / 排序（批量保存）+ 单个上下架 + 扫描同步。
  `ManifestWriter` 保留（扫描器仍需读取降级覆盖合并）；系统页「落盘降级覆盖」保留
  （仅历史遗留数据 > 0 时显示，自隐藏）。元数据改动方式 = 直接改 `tools/{id}/manifest.json`
  → 后台「扫描同步」。

- **移除后台「分类管理」模块（2026-09-19，用户定稿）**：分类的唯一真源是工具 manifest
  （`grade_range` + `subjects`），`ToolScanner::syncCategories` 每次扫描都全量重建挂载，
  后台手动增删改既影响不了挂载（slug 不在扫描器映射里永远挂不上），删预置分类反而会
  破坏前台结构。移除：`CategoryAdminController`、`views/admin/categories.php`、
  4 条 `/admin/categories*` 路由、侧栏与仪表盘入口。保留：`init_db.php` 预置的分类骨架
  （静态派生依据，非后台设置）、前台 `/category/{slug}` 全链路（验证 primary /
  primary-math / junior-physics / senior-math 全部 200）。学段/学科调整 = 改工具的
  manifest 后「扫描同步」。

- **后台内容区靠左 / 右侧大片空白修复（2026-09-19，用户反馈）**：`.admin-content` 只设了
  `max-width` 没有 `margin: auto`，宽屏下整个后台内容贴左、右侧空出数百像素。
  修复：内容区居中，并新增 `--admin-max: 1400px` 令牌（后台数据表密集，比前台 `--page-max`
  更宽以利用屏幕空间）。顺带清理仪表盘泄漏到界面的内部阶段编号「网盘链接（P1）」→「网盘链接」。

- **半成品全面排查轮（2026-09-19）**：三条线系统排查（后台配置项→前台消费交叉验证 /
  全部模板静态链接 vs 路由表比对 / 文案引用悬空扫描，CLI 审计脚本辅助），修复 4 处：
  - **关于页悬空文案条件化**：两处「通过页脚联系方式沟通」在后台未配置联系信息时悬空。
    `about.php` 现按 `SiteOps::footer()` 是否含联系方式输出两种文案，未配置时不再承诺渠道
  - **错误页文案细化**：404 / 403 / 500 分开提示；去掉错误页（无页脚）中「请联系管理员」
    这类无着陆点的泛引；404 给出「回首页 / 页头搜索」引导
  - **赞助页 AJAX 路径一致性**：「我扫了这码」上报从硬编码 `/api/sponsor/click` 改走 `url()`
  - **公众号默认文案统一**：离线包门户（PortalRenderer）与前台详情页 / 网盘中间页
    统一为「扫码关注，新工具上线第一时间通知」
  - **排查确认无问题的**：全部 30+ 配置键均有前台消费；广告位 6 槽全有渲染位；
    公告条数 / 友链申请说明 / 赞助标题 / 定制入口等均有默认文案回退；公告关闭记忆（内容指纹）
    与公告中心弹层 JS 完整实现；30 条模板静态链接全部可达（仅 /favicon.svg 为静态资源非路由）

- **后台离线包路由 404 修复（2026-09-19）**：后台侧栏「离线包打包」导航 key 为复数
  `packages`，而真实路由是单数 `/admin/package`，点击即 404。修复 `layout/admin.php`
  导航 key 改为 `package`（docblock 同步），`PackageAdminController` 两处 `render()`
  激活态参数同步。路由本身与 Router 匹配逻辑无问题（已用 CLI 诊断脚本验证全部 MATCH）。

- **页脚联系信息补全（2026-09-19）**：关于页 `/about` 两处文案引导「通过页脚联系方式沟通」，
  但页脚既无配置入口也无渲染。补全闭环：
  - 后台「导航与页脚」Tab 新增 `footer_contact_email`（联系邮箱）与
    `footer_contact_text`（其他联系方式，一行一条）
  - `SiteOps::footer()` 读取并归一化（邮箱 FILTER_VALIDATE_EMAIL 校验，合法才生成
    mailto，否则纯文本降级；联系文本按行拆分）
  - `partials/footer.php` 品牌列渲染联系列表，未配置时零痕迹
  - `site.css` 新增 `.site-footer-contact` 样式（全令牌）

- **站点图标接入 + 页脚站点地图链接修复（2026-09-19）**：
  - **站点图标**：用户提供 `KeeTools.svg` 落位 `public/favicon.svg`；`layout/site.php` 补
    `<link rel="icon" type="image/svg+xml">`（此前站点无 favicon）；页头 / 页脚 Logo 的
    `icon('grid-2x2')` 占位图标替换为站点图标 `<img>`，`site.css` 对应改图片尺寸规则
    （去掉页脚原橙色色块底，图标自带配色）。
  - **站点地图链接 Bug**：后台「导航与页脚 → 显示『站点地图』链接」开关
    （`footer_show_sitemap`）保存后前台无任何反应——根因是该开关**只有后台保存、前台从未实现**。
    修复：`SiteOps::footer()` 补读 `show_sitemap`（默认 false，与后台 `collectSwitches` 一致），
    `partials/footer.php` 备案行渲染 `/sitemap.xml` 链接（开关开启但备案号等全空时该行也会出现）。

- **移除前台页面上的后台入口（2026-09-19，用户定稿）**：页头「后台」按钮（`partials/header.php`）
  与页脚默认快捷导航中的「后台」项（`partials/footer.php`）删除，前台页面零 `/admin` 链接；
  后台设置页「快捷导航」提示文案同步（`admin/settings-footer.php`）。管理员直接访问 `/admin`
  进入；`footer_nav` 若曾自定义配置过含后台的导航仍以配置为准（后台可自行删除该行）。

### 前台

- **前台视觉改版 · 方向 B「黑板粉笔」（2026-09-19）**：三方向比选 Demo（`var/demo/index.html`，
  A 清爽课堂 / B 黑板粉笔 / C 活力拼图，10 个前台页面视图）后用户选定 B，落地为正式设计。
  纯 CSS 层换肤，PHP 模板零改动：
  - `assets-src/css/tokens.css`：主色换暖橙 `#e07b39` 系；中性色换米纸暖灰（页面底 `#faf7f0` /
    卡片 `#fffdf8` / Hero `#f0ebde`）；新增页头深板绿专用令牌 `--c-head-*`（bg `#243d33`）；
    新增装饰令牌 `--deco-bar-w / --deco-underline-th / --deco-underline-offset`；
    圆角整体收紧（r-md 6 / r-lg 8）；阴影换暖灰投影；暗色令牌同步为暖色系
  - `assets-src/css/site.css`：页头切换深板绿令牌；Hero 米纸底 + 主标题粉笔感下划线；
    `.section-title` 左侧粉笔竖条；页脚用卡片米白分层
  - `base.css` / `components.css` / 全部前台模板**未改动**，自动继承新令牌
  - 门禁：`check_css_tokens` / `build_css --check` / `check_no_external` 全部通过，产物已重建

### 文档

- **关于页 `/about` 上线（2026-09-19）**：新增 `AboutController` + `views/pages/about.php`，
  内容 = 项目介绍（工具数 / 学科数从库里实时读）+ **第三方素材版权说明**
  （Lucide ISC / Tabler MIT 图标、拼音点读音频 CC BY-SA、汉字笔顺数据 Arphic Public License、
  字体与 JS/CSS 均无第三方依赖），与 `THIRD-PARTY-LICENSES.md` 登记表对应；
  页脚默认导航加「关于」入口；`/about` 已加入动态 `/sitemap.xml`
  （sitemap 本就是 SeoController 从数据库实时生成，新工具入库后自动收录，无需手改）

- **仓库精简（2026-09-19）**：项目收官后移除已完成的历史规划文档——删除 `docs/需求文档-v2.md`、
  `docs/站点运营模块设计.md` 与整个 `tasks/` 目录（P0–P5 任务单与批次计划）；保留代码、README、
  AGENTS.md、规范文档、CHANGELOG；保留文档中的失效引用已同步改指 `CHANGELOG.md`。
  同日决策：P5（PWA / 用户系统 / 广告联盟）取消，按实际运营情况再调整

- **项目收官文档更新（2026-09-19）**：P0–P4 全部交付（57 个工具），P5（PWA / 用户系统 / 广告联盟）
  正式取消、按实际运营情况再调整——`README.md` 全面重写（功能一览 / 收官状态 / 运维命令 / 许可说明）、
  `AGENTS.md` §十一 当前阶段更新为「全量交付」、`docs/架构说明.md` 用户系统决策标注取消、
  `tasks/P5-可选.md` 状态改「已取消（保留作未来评估底稿）」

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
- **批次 6 交付：高中理科 + 信息科技（2026-09-19，8 个新工具，49 → 57）**：数学 3 / 物理 3 / 信息技术 2，全部 v1.0.0；六项门禁全绿、运行时抽检 57/57、交互态探针 8/8、入库 failed 0 / warnings 0
  - `derivative-graph`（fixed·数学）导数图像：f 与 f′ 同屏、单调区间着色、极值点、悬停切线斜率，白名单表达式解析器
  - `conic-sections`（fixed·数学）圆锥曲线：椭圆 / 双曲线 / 抛物线参数可视化，方程、焦点、准线、离心率、渐近线实时联动
  - `solid-rotation`（fixed·数学）立体几何旋转体：矩形 / 三角形 / 梯形 / 半圆绕轴扫描动画，体积与侧面积公式实时计算
  - `magnetic-field`（experiment·物理）磁场磁感线：条形磁铁 / 通电直导线 / 螺线管三种模型，磁感线积分生成，可拖小磁针探向，反转方向与线条数可调
  - `projectile-motion`（experiment·物理）平抛斜抛：初速度 / 抛射角 / 高度可调，理论轨迹虚线对照、速度分解箭头、射程与最高点读数，多球轨迹对比
  - `newton-second-law`（experiment·物理）牛顿第二定律：气垫导轨小车 a=F/m 模拟，光电门计时、数据表记录与 a–F / a–1/m 图像切换
  - `binary-converter`（fixed·信息技术）二进制转换器：2/8/10/16 进制互转、位权展开过程、8 位灯板、课堂练习判分
  - `sorting-visual`（fixed·信息技术）排序算法可视化：冒泡 / 选择 / 插入 / 快排逐帧演示，比较与交换计数、单步执行
  - 配套：`check_tools_runtime.py` 补 8 个渲染标记；交付自检修复 4 处（球体渲染成扁椭圆、Tab 高亮未随存储状态同步、螺线管内部磁感线方向画反、地面斜抛首帧误判落地）
- **批次 5 交付：史地生 + 科学长尾（2026-09-19，8 个新工具，41 → 49）**：
  历史 2 / 地理 3 / 生物 2 / 科学 1，全部 v1.0.0；六项门禁全绿、
  运行时抽检 8/8、交互态探针截图核对、入库 `scanned 49 / created 8 / failed 0 / warnings 0`
  - `dynasty-timeline`（fixed·历史）朝代时间轴：24 个朝代按存续年代成比例排布，点击看详情
  - `history-event-cards`（fixed·历史）事件卡片：64 条按 8 时期筛选
  - `lat-lon-grid`（fixed·地理）经纬网定位：拖点读数 + 半球 / 纬度带 / 温度带判定 + 定位练习
  - `map-china`（fixed·地理）中国地图：34 省级行政区拼块图，点击看简称 / 省会 / 面积 / 人口
  - `map-world`（fixed·地理）世界地图：简化海陆轮廓 + 经纬网，点选大洲 / 国家看资料
  - `cell-structure`（fixed·生物）细胞结构标注：动植物细胞切换 + 功能卡 + 识图测验
  - `blood-circulation`（fixed·生物）血液循环动画：体 / 肺循环路径动画 + 动静脉血变色
  - `moon-phases`（fixed·科学）月相变化：日地月俯视图 + 月龄滑块 / 播放 + 八相速查
- **批次 4 交付：通用容器 + 数学 + 小学科学（2026-09-19，8 个新工具，33 → 41）**，
  通用 2 / 数学 3 / 科学 3；全部 `v1.0.0`；门禁全绿、运行时抽检 41/41、探针 8/8、入库 failed 0 / warnings 0
  - `seat-chart`（shell·通用）座位表 / 排座位：名单导入排座、点击换位、随机重排、★ 标记、行列分组与讲台位置可调
  - `quiz-buzzer`（shell·通用）课堂抢答器：2–8 组按键 / 点卡抢答、首按锁定、各组记分与队名编辑
  - `unit-converter`（fixed·数学）单位换算器：长度 / 质量 / 面积 / 体积 / 时间双向实时换算 + 换算过程与进率表
  - `vertical-arithmetic`（fixed·数学）竖式计算器：加 / 减 / 乘竖式逐位演算，进位借位高亮、乘法部分积、单步 / 自动 / 出题
  - `chart-maker`（fixed·数学）统计图生成器：条形 / 折线 / 扇形即时渲染，数据行编辑与百分比图例
  - `magnet-demo`（experiment·科学）磁铁演示：拖动松手自动演示同极相斥异极相吸，磁感线可开关
  - `water-states`（fixed·科学）水的三态：温度滑杆驱动粒子晶格 / 流动 / 飞散切换，含 0 °C 熔化与 100 °C 沸腾过渡态
  - `solar-system`（fixed·科学）太阳系模型：行星按相对公转速度运行，点击查看行星档案
  - 配套：`check_tools_runtime.py` 补 8 个渲染标记；8 个交互态探针截图核对
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
