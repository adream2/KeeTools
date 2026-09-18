# 变更日志

本文件记录**项目级**变更（框架 / 前台 / 后台 / 工程规范）。
单个工具自身的变更记录在各工具目录的 `CHANGELOG.md` 中。

格式参考 [Keep a Changelog](https://keepachangelog.com/zh-CN/1.1.0/)，版本号遵循语义化版本。

---

## [未发布]

### 新增

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
