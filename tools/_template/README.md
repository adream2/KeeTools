# `tools/_template/` — 新工具骨架模板

> 以 `_` 开头的目录**不参与扫描、不参与同步、不参与打包、不进前台**（见 `AGENTS.md` §五）。
> 同时它是 `scripts/new_tool.py` 的**唯一模板来源**——模板不重复存放，改这里即改脚手架产出。

**不要直接复制这个目录**。请用脚手架生成：

```bash
# 交互式
python scripts/new_tool.py

# 非交互（批量生产推荐）
python scripts/new_tool.py --id fraction-line --title "分数数轴" \
    --type shell --subjects 数学 --grades 3-4,5-6 \
    --description "拖动分数卡片到数轴对应位置，直观比较分数大小。" --yes
```

脚手架会**自动**完成（不需要手工补）：

1. 校验 id 格式（`^[a-z0-9-]+$`），确保 id 等于目录名
2. 生成 `manifest.json`（字段顺序稳定，2 空格缩进，LF）
3. 生成 `index.html`（含 `ET-META` 块、`ET:INLINE` 标记、`et-chrome` 可用外壳）
4. 生成 `CHANGELOG.md`（初版 1.0.0）
5. 在 `tools/_shared/shared.manifest.json` 登记默认共享片段
6. 跑 `sync_shared.py <id>` 把共享片段内联进 HTML
7. 跑 `check_manifest.py` / `check_no_external.py` 自检 + **体积守卫**

---

## 模板文件

| 文件 | 产出 | 说明 |
|---|---|---|
| `index.html.tpl` | `tools/{id}/index.html` | 含 `ET-META` + `ET:INLINE` 标记 + `et-chrome` 外壳 |
| `manifest.json.tpl` | `tools/{id}/manifest.json` | 契约字段，顺序与 `docs/manifest规范.md` 一致 |
| `CHANGELOG.md.tpl` | `tools/{id}/CHANGELOG.md` | Keep a Changelog 格式 |

### 占位符

用 `@@NAME@@` 形式（**不是** `{}` / `%` —— 模板内大量 CSS 百分号与 JS 花括号，用它们会误伤）：

| 占位符 | 含义 |
|---|---|
| `@@TOOL_ID@@` | 工具 id（= 目录名） |
| `@@TITLE@@` | 工具标题 |
| `@@VERSION@@` | 版本号（初始 `1.0.0`） |
| `@@TYPE@@` | `shell` / `fixed` / `experiment` |
| `@@DESCRIPTION@@` | 一句话简介（≤ 60 字） |
| `@@AUTHOR@@` | 作者 |
| `@@SUB@@` | 顶栏副标题（学科拼接） |
| `@@ACCENT@@` | 主题色（按首个学科自动选取，可用 `--accent` 覆盖） |
| `@@PREFIX@@` | 工具自身 CSS 类名前缀（id 去连字符后前 12 字符） |
| `@@GRADES_JSON@@` / `@@SUBJECTS_JSON@@` / `@@TAGS_JSON@@` / `@@FAMILY_JSON@@` | manifest 的 JSON 片段 |
| `@@TODAY@@` | 当天日期（`YYYY-MM-DD`） |

---

## 骨架已包含的内容（不要重复造）

- `et-chrome` 外壳：顶栏 / 明暗主题 / 全屏 / 音效开关 / 帮助弹窗 / 页脚品牌回流 / PWA manifest
- 翻页笔键位：`primary`（PageDown）
- 空格键主操作（自动跳过输入框聚焦）
- `localStorage` 持久化（前缀 `et_{toolId}_`）
- 模块化统计 `et-stats`（删除该注入块即完全不联网）

**新增共享能力时**：改 `tools/_shared/` 下的源文件 → 跑 `sync_shared.py`，
**禁止**直接改工具 HTML 内 `ET:INLINE` 标记之间的内容（下次同步会被覆盖，且 `--check` 会失败）。

---

## 开发前后必读

| 内容 | 位置 |
|---|---|
| 工具开发规范（必读） | [`../../docs/工具开发规范.md`](../../docs/工具开发规范.md) |
| manifest 字段定义 | [`../../docs/manifest规范.md`](../../docs/manifest规范.md) |
| 编码规范 | [`../../docs/编码规范.md`](../../docs/编码规范.md) |
| 共享逻辑用法 | [`../_shared/README.md`](../_shared/README.md) |
| 单文件例外 / 体积超限登记 | [`../../docs/工具例外清单.md`](../../docs/工具例外清单.md) |

---

## 提交前自检

```bash
python scripts/check_manifest.py tools/<id>     # 字段 + ET-META 一致性 + 体积守卫
python scripts/check_no_external.py tools/<id>  # 零外部依赖
python scripts/sync_shared.py --check           # 共享片段已同步
```

手动验证（逐项打勾，别只看脚本）：

- [ ] 双击 `index.html`（`file://`）可用
- [ ] 断网可用，DevTools Network 无任何外部请求
- [ ] 1024×768 与 1920×1080 下都正常（大屏投影优先）
- [ ] 站点 iframe（`/tool/{id}`）内**用真实鼠标点击**回归一次（`.click()` 绕过命中测试，会掩盖交互死亡）
- [ ] 浏览器内置能力（语音 / 麦克风 / WebAudio / Canvas）均已能力检测 + 优雅降级
- [ ] 避免高版本 API：`:has()`、`structuredClone` 等高版本语法不用于**网站**；工具侧按
      `docs/工具开发规范.md` §4.1 的现代浏览器基准（Chrome/Edge 90+、Safari 14+）
- [ ] `localStorage` key 用 `et_{toolId}_` 前缀（走 `ET.store()` 自动带前缀）
- [ ] 帮助弹窗写清「怎么用 / 快捷键 / 翻页笔」
