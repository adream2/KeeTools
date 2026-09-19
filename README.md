# KeeTools · 课工具 — 中小学课堂工具集

> 面向 K12 老师的**单文件 HTML 课堂工具集**。只做工具，不做题库。
> 英文名：`KeeTools`；中文名：**课工具**

---

## 这是什么

老师需要的课堂工具（随机点名、大转盘、倒计时、计分板、拼音表……）通常散落在各种网站，要么被广告包围，要么需要注册，要么断网就废。

KeeTools 的做法是：**每个工具就是一个 HTML 文件**，双击打开就能用，断网也能用，投影到教室大屏不糊。

- 🎯 **只做工具**：不做题库、不做课程、不做作业系统
- 📦 **单文件分发**：一个 HTML = 一个工具，无需安装、无需联网
- 🖥️ **为大屏设计**：教室投影 1024×768 与大屏 1920×1080 都适配
- 🆓 **全部免费**：通过网盘合集包 / 赞助 / 站内广告维持运营
- 🔌 **零外部依赖**：不引 CDN、不引在线字体、不引在线图标

---

## 快速开始

### 环境要求

| 组件 | 版本 | 说明 |
|---|---|---|
| PHP | 8.0+ | 需启用 `pdo_sqlite`、`zip` |
| Python | 3.8+ | 仅用标准库，无需 pip 安装 |
| Git | 任意 | 版本管控 |

### 首次启动

```bash
# 1. 复制环境变量模板
cp .env.example .env
#    Windows: copy .env.example .env

# 2. 按需修改 .env（本地开发可先用 ADMIN_PASSWORDLESS=true）

# 3. 启用 git 钩子（每个 clone 需执行一次）
git config core.hooksPath .githooks

# 4. 跑一遍校验，确认环境可用
python scripts/check_manifest.py
python scripts/check_no_external.py
python scripts/sync_shared.py --check
python scripts/check_css_tokens.py

# 5. 启动开发服务器（P0 前台入口就绪后可用）
php -S localhost:8000 -t public
```

> 当前处于 **P0 骨架阶段**：`src/` 与 `public/` 尚未实现，第 5 步暂不可用。
> 数据库初始化脚本（`scripts/init_db.php`）在 P0 任务单中，尚未编写。

访问：

| 地址 | 说明 |
|---|---|
| http://localhost:8000 | 网站前台 |
| http://localhost:8000/admin | 后台管理 |

---

## 目录导航

| 目录 | 说明 |
|---|---|
| `public/` | **唯一 Web 根**，对外暴露的入口与静态资源 |
| `src/` | PHP 源码（core 框架 / admin 后台 / frontend 前台 / services 服务） |
| `assets-src/` | 需要构建的源资源（CSS 源、图标清单） |
| `tools/` | 工具本体，每个工具一个目录；`_shared/` 为开发期共享逻辑源 |
| `scripts/` | Python 工程脚本 |
| `docs/` | 规范文档 |
| `tasks/` | 分阶段任务计划表 |
| `var/` | 临时产物隔离区（不入版本管控） |
| `storage/` | SQLite 数据库（不入版本管控） |
| `packages/` | 离线合集包产出（不入版本管控） |
| `.githooks/` | git 钩子（`pre-commit` 跑六项校验） |

---

## 文档索引

| 文档 | 内容 |
|---|---|
| [`AGENTS.md`](AGENTS.md) | **AI 协作契约** — 硬约束速查，开工必读 |
| [`docs/需求文档-v2.md`](docs/需求文档-v2.md) | **唯一权威需求**（冻结版） |
| [`docs/架构说明.md`](docs/架构说明.md) | 目录结构、请求流程、模块职责 |
| [`docs/工具开发规范.md`](docs/工具开发规范.md) | 写工具前必读 |
| [`docs/manifest规范.md`](docs/manifest规范.md) | manifest 字段与校验规则 |
| [`docs/编码规范.md`](docs/编码规范.md) | 命名、缩进、提交信息 |
| [`docs/安全规范.md`](docs/安全规范.md) | 敏感操作必读 |
| [`docs/图标与许可证.md`](docs/图标与许可证.md) | 图标子集化与合规 |
| [`docs/工具例外清单.md`](docs/工具例外清单.md) | 非单文件工具的例外登记 |
| [`scripts/README.md`](scripts/README.md) | 工程脚本清单与约定 |
| [`tools/_shared/README.md`](tools/_shared/README.md) | 共享逻辑源用法 |
| [`assets-src/icons/README.md`](assets-src/icons/README.md) | 离线图标源准备方式 |
| [`tasks/`](tasks/) | 分阶段任务计划表 |

---

## 常用命令

```bash
# 校验（提交前必跑，pre-commit 已自动执行）
python scripts/check_manifest.py               # manifest 完整性 + ET-META 一致性
python scripts/check_no_external.py            # 是否有非本站域名 URL
python scripts/sync_shared.py --check          # 共享逻辑是否已同步
python scripts/check_css_tokens.py             # CSS 是否用了字面量
python scripts/build_css.py --check            # CSS 产物是否最新
python scripts/icons/build_sprite.py --check   # sprite 产物是否最新

# 工具运行时抽检（非门禁；每批工具交付前必跑，缺 Node / 浏览器自动跳过）
python scripts/check_tools_runtime.py          # JS 语法（node --check）+ 无头渲染（DOM / 截图 / 报错）

# 构建
python scripts/build_css.py                    # 生成 public/assets/css/
python scripts/icons/build_sprite.py           # 生成图标 sprite
python scripts/sync_shared.py                  # 同步共享逻辑到所有工具

# 开发
php -S 127.0.0.1:8000 -t public public/router.php   # 启动开发服务器（须带 router.php）
```

---

## 分期规划

| 阶段 | 目标 | 状态 |
|---|---|---|
| P0 | 工程规范 + 框架地基 + 扫描入库 + 前后台基础 | 🚧 进行中 |
| P1 | 详情页 + 在线使用 + 网盘中间页 + 统计 | ⏳ |
| P2 | 3-5 个标杆工具验证规范 | ⏳ |
| P3 | 离线合集包 + 本地导航门户 | ⏳ |
| P4 | 按学科规模化 + SEO | ⏳ |
| P5 | PWA / 用户系统 / 广告联盟（可选） | ⏳ |

详见 [`tasks/`](tasks/)。

---

## 开发约定（三条最重要的）

1. **零外部依赖** — 任何产出物中不得出现第三方域名 URL
2. **临时产物只写 `var/`** — 不得污染 `src/`、`public/`、`tools/` 等架构目录
3. **共享逻辑只改 `_shared/`** — 禁止手工复制粘贴，改完跑 `python scripts/sync_shared.py`

完整约束见 [`AGENTS.md`](AGENTS.md)。

---

## 许可证

- 项目代码：待定
- 第三方资源：见 [`THIRD-PARTY-LICENSES.md`](THIRD-PARTY-LICENSES.md)
