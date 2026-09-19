# KeeTools · 课工具 — 中小学课堂工具集

> 面向 K12 老师的**单文件 HTML 课堂工具集**。只做工具，不做题库。
> 英文名：`KeeTools`；中文名：**课工具**
>
> **当前状态：全量交付（2026-09-19）** — 57 个工具，前台 / 后台 / 统计 / 离线打包全链路可用。

---

## 这是什么

老师需要的课堂工具（随机点名、大转盘、倒计时、计分板、拼音表……）通常散落在各种网站，要么被广告包围，要么需要注册，要么断网就废。

KeeTools 的做法是：**每个工具就是一个 HTML 文件**，双击打开就能用，断网也能用，投影到教室大屏不糊。

- 🎯 **只做工具**：不做题库、不做课程、不做作业系统
- 📦 **单文件分发**：一个 HTML = 一个工具，无需安装、无需联网
- 🖥️ **为大屏设计**：教室投影 1024×768 与大屏 1920×1080 都适配
- 🔌 **零外部依赖**：不引 CDN、不引在线字体、不引在线图标

---

## 功能一览

| 板块 | 内容 |
|---|---|
| **网站前台** | 首页 / 工具列表 / 搜索 / 学段×学科分类页 / 工具详情页 / 在线使用页（iframe 同源）/ 网盘中间页 / 下载 / 赞助 / 友链 |
| **后台管理** | 工具管理（manifest 回写文件）/ 分类导语 / 网盘配置 / 统计看板 / 打包任务 / 站点设置 |
| **统计** | 服务端向页面注入上报脚本（工具零改造）；事件白名单 view / use_online / use_offline / netdisk_click / download_direct / sponsor_click |
| **离线合集包** | 后台一键按学段打包 < 10MB zip，内含本地导航门户（PortalRenderer） |
| **工具规模** | **57 个**，覆盖通用 / 语文 / 数学 / 英语 / 物理 / 化学 / 生物 / 历史 / 地理 / 科学 / 信息技术 |

---

---

## 快速开始

### 环境要求

| 组件 | 版本 | 说明 |
|---|---|---|
| PHP | 8.0+ | 需启用 pdo_sqlite、zip |
| Python | 3.8+ | 仅标准库，无需 pip |
| Git | 任意 | 启用 .githooks 钩子需本地配置 |

### 首次启动

```bash
# 1. 复制环境变量模板并按需修改（本地开发可先 ADMIN_PASSWORDLESS=true）
cp .env.example .env          # Windows: copy .env.example .env

# 2. 初始化数据库（SQLite，WAL 模式）
php scripts/init_db.php

# 3. 启用 git 钩子（每个 clone 需执行一次，pre-commit 自动跑六项校验）
git config core.hooksPath .githooks

# 4. 启动开发服务器（必须带 router.php）
php -S 127.0.0.1:8000 -t public public/router.php
```

首次启动后，进入后台「工具管理 → 扫描同步」即可把 `tools/` 下全部工具入库（也可等定时任务自动扫描）。

| 地址 | 说明 |
|---|---|
| http://127.0.0.1:8000 | 网站前台 |
| http://127.0.0.1:8000/admin | 后台管理 |

---

## 目录导航

| 目录 | 说明 |
|---|---|
| `public/` | **唯一 Web 根**，对外暴露的入口与静态资源 |
| `src/` | PHP 源码（core 框架 / admin 后台 / frontend 前台 / services 服务） |
| `assets-src/` | 需要构建的源资源（CSS 源、图标清单） |
| `tools/` | 工具本体（57 个），每个工具一个目录；`_shared/` 为共享逻辑源 |
| `scripts/` | Python 工程脚本（少量 PHP 运维脚本） |
| `docs/` | 规范文档（工具开发 / manifest / 编码 / 安全等） |
| `var/` | 临时产物隔离区（不入版本管控） |
| `storage/` | SQLite 数据库（不入版本管控） |
| `packages/` | 离线合集包产出（不入版本管控） |
| `.githooks/` | git 钩子（pre-commit 跑六项校验） |

---

## 常用命令

```bash
# 校验（提交前必跑，pre-commit 已自动执行六项门禁）
python scripts/check_manifest.py               # manifest 完整性 + ET-META 一致性
python scripts/check_no_external.py            # 是否有非本站域名 URL
python scripts/sync_shared.py --check          # 共享逻辑是否已同步
python scripts/check_css_tokens.py             # 网站 CSS 是否用了字面量
python scripts/build_css.py --check            # CSS 产物是否最新
python scripts/icons/build_sprite.py --check   # 图标 sprite 是否最新

# 工具运行时抽检（非门禁；交付前必跑，缺 Node / 浏览器自动跳过）
python scripts/check_tools_runtime.py          # JS 语法 + 无头渲染（DOM / 截图 / 报错）

# 构建
python scripts/build_css.py                    # 生成 public/assets/css/
python scripts/icons/build_sprite.py           # 生成图标 sprite
python scripts/sync_shared.py                  # 同步共享逻辑到所有工具

# 开发服务器（必须带 router.php）
php -S 127.0.0.1:8000 -t public public/router.php

# 运维（PHP）
php scripts/init_db.php                        # 初始化数据库
php scripts/flush_stats.php                    # 统计缓冲落库（cron 每分钟）
php scripts/build_package.php --list           # 打包任务概览
```

---

## 开发约定（三条最重要的）

1. **零外部依赖** — 任何产出物中不得出现第三方域名 URL
2. **临时产物只写 `var/`** — 不得污染 `src/`、`public/`、`tools/` 等架构目录
3. **共享逻辑只改 `tools/_shared/`** — 禁止手工复制粘贴，改完跑 `python scripts/sync_shared.py`

完整约束见 [`AGENTS.md`](AGENTS.md)。新增工具一律走 `python scripts/new_tool.py` 脚手架，禁止手抄目录。

---

## 许可证

- 项目源码：私有仓库，暂未定开源许可（对外分发的只有工具 HTML 与离线合集包，工具本体对使用者免费）
- 第三方资源：见 [`THIRD-PARTY-LICENSES.md`](THIRD-PARTY-LICENSES.md)（如 hanzi-writer-data 笔迹数据，Arphic Public License）
