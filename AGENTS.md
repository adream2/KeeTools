# AGENTS.md — AI 协作契约

> 本文件面向所有 AI 编码助手（Agent）。开工前**必须**通读本文件与 `docs/` 下的规范。
> 最后更新：2026-09-19

---

## 一、项目一句话说明

**KeeTools**（中文名「**课工具**」，仓库 / 工作区名 `KeeTools`）—— 面向中小学老师的**单文件 HTML 课堂工具集**。只做工具，不做题库。工具全免费，靠网盘分发（合集包）/ 赞助 / 站内广告 / 工具定制变现。

---

## 二、开工前必读顺序

| 顺序 | 文件 | 说明 |
|---|---|---|
| 1 | `AGENTS.md` | 本文件（硬约束速查） |
| 2 | `docs/架构说明.md` | 目录结构、请求流程、模块职责 |
| 3 | `docs/工具开发规范.md` | 写工具前必读 |
| 4 | `docs/manifest规范.md` | manifest 字段与校验 |
| 5 | `docs/编码规范.md` | 命名、缩进、提交信息 |
| 6 | `docs/安全规范.md` | 敏感操作前必读 |
| 7 | `docs/图标与许可证.md` | 图标来源与合规要求 |
| 8 | `CHANGELOG.md` | 历史决策与变更记录（原需求文档已随项目收官移除，决策以本文件为准） |

**首次检出仓库后必须执行一次**（否则 `pre-commit` 生效不了）：

```bash
git config core.hooksPath .githooks
```

---

## 三、硬约束（违反即视为错误）

### 3.1 零外部依赖

> **禁止**在任何产出物中引用第三方域名资源。

- ❌ 禁止 CDN（含 Tailwind Play CDN、Google Fonts、jsDelivr、unpkg）
- ❌ 禁止在线字体、在线图标库
- ✅ 所有资源本地化，图标走 `public/assets/icons/sprite.svg`

验收命令：

```bash
python scripts/check_no_external.py
```

### 3.2 工具与网站解耦

工具（`tools/{id}/`）**必须**：

- ❌ 不与网站后端通信
- ❌ 不引用网站任何资源（CSS / JS / 图标 / 图片）
- ❌ 不统计任何内部操作
- ✅ 只通过 `manifest.json` 与网站建立契约
- ✅ 数据持久化只用 `localStorage`，key 前缀 `et_{toolId}_{key}`

### 3.3 版本号规则

- ✅ 版本号写在：`manifest.json`、`tools/{id}/CHANGELOG.md`、HTML 内 `ET-META`、页脚展示
- ❌ **URL 绝不含版本号**（永久路径 `/tool/{id}`）

### 3.4 临时产物隔离

> 临时文件、构建中间产物、调试输出、导出草稿 **一律写入 `var/`**。

- 允许位置：`var/cache/`、`var/tmp/`、`var/logs/`、`var/exports/`
- ❌ **禁止**污染：仓库根、`src/`、`public/`、`assets-src/`、`tools/`
- `var/` 全量 gitignore，可随时整体删除

### 3.5 禁止提交的内容

- ❌ `.env`（只提交 `.env.example`）
- ❌ `storage/*.db`
- ❌ `var/` 下任何文件
- ❌ `packages/*.zip`
- ❌ `node_modules/`、`__pycache__/`、`.DS_Store`

### 3.6 共享逻辑不得手工复制

多个工具需要同一段逻辑时：

```bash
# ✅ 正确：改 _shared，跑同步脚本
vim tools/_shared/logic/random-pick.js
python scripts/sync_shared.py

# ❌ 错误：直接改工具内的内联副本
```

工具 HTML 内使用标记，**标记之间的内容由脚本生成，禁止手工编辑**：

```html
<!-- ET:INLINE name="random-pick" src="logic/random-pick.js" -->
<script>
  /* 此块由 scripts/sync_shared.py 自动注入，请勿手工修改 */
  ...
</script>
<!-- /ET:INLINE -->
```

- `type="css"` / `type="svg"` 可显式声明；不写则按扩展名推断（`.css`→css，`.svg`→svg，其余→js）
- `src` 必须落在 `tools/_shared/` 内（脚本做路径穿越防护）
- 片段名与路径可在 `tools/_shared/shared.manifest.json` 集中登记

提交前检查：`python scripts/sync_shared.py --check`

---

## 四、技术栈（不得擅自更换）

| 层 | 技术 |
|---|---|
| 网站前端 | 原生 HTML + **自写 CSS**（四层：tokens / base / components / site）+ 原生 JS |
| 网站图标 | Iconify 子集 → SVG sprite（`scripts/icons/build_sprite.py`） |
| 网站后端 | PHP 8+，自研轻量框架 |
| 数据库 | SQLite（WAL）+ PDO，**不做 ORM / 抽象层** |
| 工程脚本 | **Python 3**（标准库优先，不引入 pip 依赖） |
| 工具本体 | 纯 HTML/CSS/JS，资源内联 |
| 版本管控 | Git |

> ⚠️ **不要**擅自引入 Tailwind、Bootstrap、jQuery、Vue、React、Composer 依赖、npm 依赖。

---

## 五、目录职责（只写这里）

| 目录 | 职责 | 可写 |
|---|---|---|
| `public/` | **唯一 Web 根**，对外暴露 | 构建产出 |
| `src/` | PHP 源码（不对外暴露） | ✅ 主要工作区 |
| `assets-src/` | CSS 源 / 图标清单（需构建） | ✅ |
| `tools/` | 工具本体 + `_shared/`（`_` 前缀目录不参与扫描/分发） | ✅ |
| `scripts/` | Python 工程脚本 | ✅ |
| `docs/` | 规范文档 | ✅ |
| `var/` | 临时产物 | ✅（不入 git） |
| `storage/` | 数据库 / 持久化数据 | 运行时写入 |
| `packages/` | 离线包产出 | 后台生成 |
| `.githooks/` | git 钩子（`pre-commit` 跑六项校验） | 需 `core.hooksPath` 指向此处 |

各目录内的 `README.md` 说明该目录的局部约定：

| 位置 | 说明 |
|---|---|
| `scripts/README.md` | 脚本清单、`--check` 模式、降级行为、脚本编写约定 |
| `tools/_shared/README.md` | 共享逻辑源的用法与硬规则 |
| `tools/_template/README.md` | 工具开发模板与提交前自检 |
| `assets-src/icons/README.md` | 离线图标源准备方式 |

---

## 六、编码规范速查

### 6.1 通用

- 缩进：**4 空格**（PHP / JS / CSS），**2 空格**（YAML / JSON）
- 换行：**LF**
- 编码：**UTF-8 无 BOM**
- 行尾不留空格；文件末尾保留一个空行

### 6.2 PHP

- `declare(strict_types=1);`
- 类名 `PascalCase`，方法/变量 `camelCase`，常量 `UPPER_SNAKE`
- 始终使用 PDO 参数化查询，**禁止**字符串拼接 SQL
- 输出一律 `htmlspecialchars($v, ENT_QUOTES, 'UTF-8')`
- 所有 POST 端点必须校验 CSRF token

### 6.3 JavaScript

- 工具代码：**现代浏览器优先**（Chrome/Edge 90+、Safari 14+，2026-09-18 修订，详见 `docs/工具开发规范.md` §4.1），可用 ES2018+；引用浏览器内置能力须能力检测 + 优雅降级
- 网站代码：可用 ES2018（前台访客浏览器较新），但仍避免 `?.` 以外的新语法滥用
- 变量 `camelCase`，常量 `UPPER_SNAKE`

### 6.4 CSS

- 类名 `kebab-case`，使用语义前缀：`.btn-`、`.card-`、`.form-`、`.list-`
- `base.css` / `components.css` / `site.css` **禁止字面量颜色与尺寸**，必须 `var(--...)`
- 工具内 CSS 可自由（工具自包含，不受网站令牌约束）

### 6.5 Python

- 标准库优先，**不新增 pip 依赖**
- 脚本入口统一 `if __name__ == "__main__":`
- 输出中文提示，退出码：`0` 成功、`1` 校验失败

---

## 七、常用命令

```bash
# ── 校验类（提交前必跑，已由 pre-commit 自动执行）
python scripts/check_manifest.py               # manifest 完整性 + ET-META 一致性
python scripts/check_no_external.py            # 检查是否有非本站域名 URL
python scripts/sync_shared.py --check          # 检查共享逻辑是否已同步
python scripts/check_css_tokens.py             # 检查 CSS 是否用了字面量
python scripts/build_css.py --check            # 检查 CSS 产物是否为最新
python scripts/icons/build_sprite.py --check   # 检查 sprite 产物是否为最新

# ── 工具运行时抽检（非门禁，每批工具交付前必做；缺 Node / 浏览器自动跳过）
python scripts/check_tools_runtime.py          # JS 语法（node --check）+ 无头渲染（DOM/截图/报错）

# ── 构建类
python scripts/build_css.py                    # 生成 public/assets/css/
python scripts/icons/build_sprite.py           # 生成图标 sprite（离线）
python scripts/icons/prepare_source.py         # 提取图标离线源（唯一联网脚本，低频）
python scripts/sync_shared.py                  # 同步共享逻辑到所有工具
python scripts/sync_shared.py <tool-id>        # 只同步指定工具

# ── 本地开发
php -S 127.0.0.1:8000 -t public public/router.php   # 启动开发服务器（须带 router.php）
python -m http.server 8000 --directory public       # 纯静态预览（无 PHP 路由）

# ── Git
git status
git add -A
git commit -m "feat(tools): 新增随机点名器"
```

---

## 八、Git 提交规范

格式：`<type>(<scope>): <subject>`

| type | 含义 |
|---|---|
| `feat` | 新功能 |
| `fix` | 修复 |
| `docs` | 文档 |
| `style` | 格式（不影响逻辑） |
| `refactor` | 重构 |
| `perf` | 性能 |
| `build` | 构建 / 脚本 |
| `chore` | 杂项 |
| `tool` | 新增或更新工具（scope 填工具 id） |

示例：

```
feat(frontend): 详情页增加网盘主按钮
fix(admin): 修复 manifest 回写时中文转义
tool(random-name-classic): v1.2.0 支持加权抽取
docs: 补充设计令牌说明
```

**禁止**：提交信息为 `update`、`fix`、`修改` 等无意义描述；**禁止**一次提交混合多个不相关改动。

---

## 九、返回结果给用户时

- 说明**改了什么文件、为什么**
- 列出**验证方式**（跑了什么命令、结果如何）
- 若引入新文件，说明它在架构中的位置
- 若与既有架构 / 已定决策（见 `CHANGELOG.md`）有冲突，**先指出冲突**，不要擅自决定

---

## 十、需要先问用户的情况

| 情况 | 说明 |
|---|---|
| 需要引入新的第三方依赖 | 与"零外部依赖"冲突，必须先确认 |
| 需要新建顶层目录 | 影响架构，必须先确认 |
| 需要推翻既有冻结决策（分期规划 / 能力取舍） | 属于契约变更，必须先确认 |
| 工具无法做到单文件 | 需登记例外清单，先确认 |
| 需要写 `var/` 之外的文件 | 可能是架构污染，先确认 |

---

## 十一、当前阶段

- **阶段**：全量交付（2026-09-19）— P0–P4 全部完成，57 个工具，前台 / 后台 / 统计 / 离线打包全链路可用
- **P5（PWA / 用户系统 / 广告联盟）**：已取消（2026-09-19 定），后续按实际运营情况调整，不预建
- **日常维护入口**：新增工具走 `scripts/new_tool.py` 脚手架 → 实现 → 六项门禁 + `check_tools_runtime.py` → CLI/后台扫描入库 → 落 CHANGELOG 与任务单；改动前通读本文件与 `docs/` 规范
