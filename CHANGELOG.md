# 变更日志

本文件**只记录网站（框架 / 前台 / 后台 / 工程规范 / 构建脚本）的变更**。
工具自身的改动不进本文件——每个工具都有独立的 `tools/{id}/CHANGELOG.md`，
工具的版本、修复与新增一律记在那里。

格式参考 [Keep a Changelog](https://keepachangelog.com/zh-CN/1.1.0/)，版本号遵循语义化版本。

---

## [未发布]

### 构建脚本

- **新增数据管线脚本 `scripts/gen_hanzi_pinyin.py`（2026-09-21）**：生成 `tools/pinyin-to-words` 的
  内置拼音字表（GB2312 一级常用字 3755 字，源为 mozillazg/pinyin-data，MIT）。
  字集由 Python 标准库 `gb2312` 编解码现场枚举得到，**不引入任何外部字表文件**（避开教材编排 / 版权问题）；
  每字取第一读音、保留声调，压缩为 `一yī丁dīng…` 单字符串（约 26KB）注入工具 HTML 的 `PYW_DICT` 标记块，
  支持 `--check`（供门禁校验工具内字表是否与生成结果一致）。产物随工具内联，**运行时不联网**。
  与 `gen_china_map.py` / `gen_world_map.py` 同属「低频联网脚本，产物内联」模式，`scripts/README.md` 已登记。

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

- **manifest 新增可选字段 `requires`（运行环境要求，2026-09-20）**：工具只声明所需的浏览器能力
  （`microphone` / `camera`），站点据此做两件事：① 给详情页在线预览 iframe 补 Permissions Policy
  `allow="fullscreen; microphone"`——**不加这一句，iframe 内 `getUserMedia()` 必被浏览器拒绝**，
  这是麦克风类工具最容易漏的一环；② 渲染「使用要求」卡片 + 工具头警示标签。
  中文措辞集中在 `tool_requires_items()`（改一次全站生效），工具侧只写能力 key，避免各写一套说法。
  落地链路：`docs/manifest规范.md` §3.6 → `check_manifest.py` / `ToolScanner::validate` →
  `tools.requires` 列（`init_db.php` 建表 + 旧库缺列自动 `ALTER`，扫描器 upsert 前亦自愈）→
  `ToolRepository::hydrate` → `pages/tool.php`（标签 / 预览提示 / 要求卡片）。
  另：`file://` 能否取麦取决于浏览器策略（Chrome 视其为可信来源、Safari 等直接拒绝），
  故文档与文案一律写成**区间表述**，禁止写死"本地文件一定不可用"。

### 修复

- **fix(frontend): 「全部工具」页只列 60 个工具，「找到 N 个工具」计数被硬上限截断（2026-09-21）**：
  `ToolRepository::latest()` / `search()` 默认 `$limit = 60`，而 `SearchController`（`/tools` 与 `/search`）
  调用时不传参，于是工具数超过 60 后列表和页头计数一起停在 60；`/about` 用 `count(latest(1000))`
  计数也属于同类隐患（超 1000 即失准）。改为：`latest()` / `search()` 默认 `$limit = 0` **表示不限制**，
  SQL 仅在 `$limit > 0` 时拼 `LIMIT`；新增 `ToolRepository::publishedCount()`（`COUNT(*)` 直查）
  供 `/about` 使用，彻底摆脱硬编码上限。
  注：分类页 `byCategorySlug()` 的列表仍有 `LIMIT 200`（其 `total` 走聚合计数、不受影响），阈值远超当前规模，暂不调整。

- **fix(frontend): 详情页预览框高度被 16:9 锁死（2026-09-20）**：删掉 `site.css` 里遗留的旧 `.tool-preview`（带 `aspect-ratio: 16/9`，其 `.tool-preview iframe{height:100%}` 优先级压过新版 `.preview-frame` 高度），预览框恢复 `min(70vh, …)`。
- **fix(tools): 外壳 body 默认外边距清零（2026-09-20）**：`tools/_shared/ui/et-chrome.css` 新增 `body.et-app { margin: 0 }`，`sync_shared.py` 同步 58 个工具。此前浏览器默认 8px 使 iframe 内文档高出 16px，预览框底部露白边且可微滚动。

- **third-party.json 迁入运行时目录（2026-09-20）**：第三方素材登记唯一真源从
  `assets-src/third-party.json` 移至 `src/data/third-party.json`。原因：`/about` 页由
  `ThirdPartyNotices` 在运行时读取该 JSON 渲染版权登记表，而 `assets-src/` 属构建源目录
  不进入生产环境，导致线上 `/about` 版权表降级。同步更新 `ThirdPartyNotices` /
  `AboutController` docblock / `about.php` 文案 / `gen_licenses.py` / `check_no_external.py`
  豁免表 / `docs/图标与许可证.md`；`THIRD-PARTY-LICENSES.md` 重新生成。
  assets-src/ 其余文件仍为纯构建源，不部署；线上 `/about` 已复验恢复。

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
  - **排查确认无问题的**：全部 30+ 配置键均有前台消费；
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

- **本文件收敛为「网站变更日志」（2026-09-20，用户定稿）**：工具明细（各批次交付清单、逐工具版本与
  修复详情）从本文件移除——工具变更只记 `tools/{id}/CHANGELOG.md`；同时剔除广告位 / 运营理念类内容。
  `AGENTS.md` 必读表与「当前阶段」同步该口径。

- **关于页 `/about` 上线（2026-09-19）**：新增 `AboutController` + `views/pages/about.php`，
  内容 = 项目介绍（工具数 / 学科数从库里实时读）+ **第三方素材版权说明**
  （Lucide ISC / Tabler MIT 图标、拼音点读音频 CC BY-SA、汉字笔顺数据 Arphic Public License、
  字体与 JS/CSS 均无第三方依赖），与 `THIRD-PARTY-LICENSES.md` 登记表对应；
  页脚默认导航加「关于」入口；`/about` 已加入动态 `/sitemap.xml`
  （sitemap 本就是 SeoController 从数据库实时生成，新工具入库后自动收录，无需手改）

- **仓库精简（2026-09-19）**：项目收官后移除已完成的历史规划文档——删除 `docs/需求文档-v2.md`、
  `docs/站点运营模块设计.md` 与整个 `tasks/` 目录（P0–P5 任务单与批次计划）；保留代码、README、
  AGENTS.md、规范文档、CHANGELOG；保留文档中的失效引用已同步改指 `CHANGELOG.md`。

- **项目收官文档更新（2026-09-19）**：`README.md` 全面重写（功能一览 / 收官状态 / 运维命令 /
  许可说明）、`AGENTS.md` §十一 当前阶段更新为「全量交付」

### 新增
- **共享层：`et-import` 支持自定义字段模式（2026-09-19）**
  - `tools/_shared/ui/et-import.js` **加法式扩展**：新增 `fields: [{key, label, keys}]` 选项，
    用于导入非「姓名 / 学号 / 权重」结构的表（如单词卡片的 单词 / 音标 / 释义）；
    列名按 `keys` 多别名匹配，表头识别、CSV/TSV/JSON 解析与结果预览一并适配
  - **未传 `fields` 时行为与旧版完全一致**（`random-name-classic` / `wheel-spinner` /
    `scoreboard` / `random-grouping` / `dictation-helper` 等不受影响）；已同步至全部工具

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
    - **三项待决策定稿**：不引入 CI（单人开发，CI 覆盖不到部署自检）；工具总数软上限 100；
      不做"用户提交工具"入口（V1 无用户系统，用定制需求入口承接）

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

- **P2 共享基建与工具外壳（2026-09-18）**
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
