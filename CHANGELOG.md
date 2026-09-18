# 变更日志

本文件记录**项目级**变更（框架 / 前台 / 后台 / 工程规范）。
单个工具自身的变更记录在各工具目录的 `CHANGELOG.md` 中。

格式参考 [Keep a Changelog](https://keepachangelog.com/zh-CN/1.1.0/)，版本号遵循语义化版本。

---

## [未发布]

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
