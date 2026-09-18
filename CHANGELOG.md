# 变更日志

本文件记录**项目级**变更（框架 / 前台 / 后台 / 工程规范）。
单个工具自身的变更记录在各工具目录的 `CHANGELOG.md` 中。

格式参考 [Keep a Changelog](https://keepachangelog.com/zh-CN/1.1.0/)，版本号遵循语义化版本。

---

## [未发布]

### 新增

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
