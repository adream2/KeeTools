# 参与共建指南（CONTRIBUTING）

> 感谢你考虑为 KeeTools 课工具贡献代码、素材或想法！
> 本文档说明贡献方式、质量门槛与版权要求。开工前请先通读 [AGENTS.md](AGENTS.md)。

---

## 我们需要什么样的贡献

| 方式 | 门槛 | 说明 |
|---|---|---|
| **提需求 / 报问题** | ⭐ | 在 [Issues](../../issues) 提出，用对应模板 |
| **完善文档** | ⭐⭐ | 错别字、使用说明、教程 |
| **共享逻辑改进** | ⭐⭐⭐ | `tools/_shared/` 下的共享片段（改一处，全部工具受益） |
| **新工具 / 工具改进** | ⭐⭐⭐⭐ | 核心贡献，见下方完整流程 |

**我们只做工具，不做题库**：题库、课程、作业系统类提案不会被接受。

---

## 新工具完整流程

### 1. 先提案，再动手

开一个 [工具提案 Issue](../../issues/new?template=tool-request.md) 描述：
工具解决什么课堂场景、目标学段学科、同类工具有什么不足。
维护者确认后你再动手，避免白做。

### 2. 搭建开发环境

```bash
git clone git@github.com:adream2/KeeTools.git
cd KeeTools
git config core.hooksPath .githooks   # 启用 pre-commit 六项校验
cp .env.example .env
php scripts/init_db.php
php -S 127.0.0.1:8000 -t public public/router.php
```

### 3. 生成工具骨架

```bash
python scripts/new_tool.py --id my-tool --title "我的工具" \
    --type shell --subjects 通用 --grades 1-12 --yes
```

### 4. 实现与自检

- 必读：[`docs/工具开发规范.md`](docs/工具开发规范.md)、[`docs/manifest规范.md`](docs/manifest规范.md)
- 硬约束速记：
  - 一个工具 = 一个目录，`index.html` 双击（`file://`）必须能用、断网必须能用
  - **零 CDN 依赖**：不引用任何外部链接（CDN / 在线字体 / 在线图标），确保无网可用；CI 产出物扫描非本站域名即红
  - 数据只存 `localStorage`，key 前缀 `et_{toolId}_`
  - 共享逻辑只改 `tools/_shared/`，跑 `python scripts/sync_shared.py` 同步
  - 升版本必须同步：`manifest.json` / `ET-META` / `CHANGELOG.md` / `updated_at`

自检命令：

```bash
python scripts/check_manifest.py
python scripts/check_no_external.py
python scripts/sync_shared.py --check
python scripts/check_css_tokens.py
python scripts/build_css.py --check
python scripts/check_tools_runtime.py   # 需本机有 Node / 浏览器，CI 之外建议必跑
```

### 5. 提交 Pull Request

- 一个 PR 只做一件事
- 按 [PR 模板](.github/PULL_REQUEST_TEMPLATE.md) 逐项勾选
- CI 会自动跑门禁，全绿后维护者人工审核**教学适用性**与素材版权

---

## 版权与素材要求（重要）

- 你提交的代码必须是**原创**或你**有权再授权**的内容
- 引用第三方素材（图标 / 音频 / 数据集）必须：
  1. 允许再分发，且注明来源与许可证
  2. 在 [`THIRD-PARTY-LICENSES.md`](THIRD-PARTY-LICENSES.md) 登记并在 `src/views/pages/about.php` 说明
- 禁止录入任何个人隐私信息（学生真实名单等不得作为示例数据入库）

## 许可证

本项目采用 **[GNU AGPL-3.0](LICENSE)**。
你贡献的内容将被包含在 AGPL-3.0 协议下对外发布，请确认你能接受。

## 行为准则

- 对事不对人，友善讨论
- 教学场景优先：一切争论以「是否帮助课堂」为准绳
