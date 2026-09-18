# 第三方资源许可证登记

> 本项目遵循**零外部依赖**原则：运行时不得引用任何第三方域名资源。
> 本文件登记所有**内联打包**进产出物的第三方资源及其许可证。
>
> 最后更新：2026-09-18

---

## 登记规则

新增任何第三方资源（图标 / 字体 / JS 库 / CSS 片段）时，必须：

1. 确认许可证允许商用
2. 在本文件新增条目
3. 若许可证要求署名（如 CC-BY），在产出的页面中保留声明
4. 若来源为 npm/CDN，需一并下载到 `assets-src/` 或 `tools/_shared/` 后内联

**禁令**：

- ❌ 不得引用运行时外部 URL
- ❌ 不得使用许可证不明或禁止商用的资源
- ❌ 不得使用带商标限制的图标集（如 `logos:`、`simple-icons:`）用于商业界面

---

## 一、图标

| 图标集 | 来源 | 许可证 | 商用 | 署名要求 | 用途 |
|---|---|---|---|---|---|
| Lucide | Iconify `lucide:*` | ISC | ✅ | 无需（建议保留） | 网站 UI 图标 |
| Tabler Icons | Iconify `tabler:*` | MIT | ✅ | 无需 | 网站 UI 图标 |

**使用方式**：通过 `scripts/icons/build_sprite.py` 按需子集化，产出 `public/assets/icons/sprite.svg`，仅包含 `assets-src/icons/icons.txt` 中声明的图标。

**已内置图标集明细**：

| 图标集 | 内置形式 | 图标数 | 获取方式 | 获取日期 | 上游快照 |
|---|---|---|---|---|---|
| Lucide | `assets-src/icons/lucide.json`（Iconify JSON 子集） | 46（+4 别名） | `python scripts/icons/prepare_source.py`（**唯一联网脚本**，一次性提取后入库） | 2026-09-18 | `lastModified=1789279477`（2026-09-13） |

**工具图标**：位于 `tools/_shared/icons/`，同样来源于上述宽松许可证图标集，内联进工具 HTML。

### 待补充

- [ ] 若后续引入 Material Symbols（Apache-2.0），需在此登记并在页面保留 NOTICE

---

## 二、字体

| 字体 | 来源 | 许可证 | 用途 |
|---|---|---|---|
| （无） | — | — | 全程使用**系统字体栈**，不引入任何字体文件 |

**系统字体栈**（定义于 `assets-src/css/tokens.css`）：

```css
--ff-sans: "Microsoft YaHei", "PingFang SC", "Hiragino Sans GB",
           system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
```

---

## 三、JavaScript 库

| 库 | 版本 | 许可证 | 位置 | 用途 |
|---|---|---|---|---|
| （无） | — | — | — | 全程使用原生 JS，不引入任何 JS 库 |

---

## 四、CSS 框架

| 框架 | 版本 | 许可证 | 说明 |
|---|---|---|---|
| （无） | — | — | 自写 CSS（tokens / base / components / site），见 `docs/需求文档-v2.md` §3.1 |

> 兜底说明：若 P1 阶段决定切换至 TailwindCSS 本地构建产物，需在此登记 Tailwind 版本与 MIT 许可证，并说明"仅使用本地构建产物，无运行时外部依赖"。

---

## 五、数据与素材

| 资源 | 来源 | 许可证 | 用途 |
|---|---|---|---|
| （待补充） | — | — | 工具内置知识数据（拼音表 / 元素周期表等）的版权状态需逐项确认 |

> ⚠️ **注意**：`fixed` 型工具的内置数据可能涉及教材配套资料版权，登记前需确认来源合法性或仅使用公共领域 / 自编数据。

---

## 六、审计记录

| 日期 | 操作 | 说明 |
|---|---|---|
| 2026-09-18 | 建立登记表 | 初始登记，当前仅 Lucide + Tabler |
