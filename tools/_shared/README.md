# `tools/_shared/` — 共享逻辑源

> 这里**不是**工具，不会被扫描、不会被打包、不会出现在前台。
> 它只是"开发期的逻辑源"，由 `scripts/sync_shared.py` 幂等内联进各工具 HTML。

---

## 为什么要它

工具必须物理独立（双击即用、断网可用、单文件分发），但**同一段逻辑不应该在 N 个文件里重复维护**。

做法：逻辑只写在这里一份 → 跑同步脚本 → 内容被内联进各工具的 `ET:INLINE` 标记之间。

```
tools/_shared/logic/random-pick.js
        ↓ python scripts/sync_shared.py
tools/random-name-classic/index.html   ← ET:INLINE 块被替换
tools/random-name-emoji/index.html     ← 同一份代码
```

---

## 目录结构

```
tools/_shared/
├── logic/                  # 纯逻辑，无 DOM 依赖
│   ├── random-pick.js
│   ├── countdown.js
│   └── scoreboard.js
├── ui/                     # UI 片段（含样式）
│   ├── et-import.js        # 通用数据导入组件（重点基建）
│   ├── et-import.css
│   └── et-chrome.js        # 全屏工具条 + 品牌回流页脚
├── icons/
│   └── icons.svg           # 工具图标 symbol 集合（独立于网站 sprite）
├── shared.manifest.json    # 声明"哪个工具引用了哪些片段"
└── README.md
```

---

## 使用方式

### 1. 工具 HTML 内写标记

```html
<!-- ET:INLINE name="random-pick" src="logic/random-pick.js" -->
<script>/* 由 sync_shared.py 自动注入，请勿手工修改 */</script>
<!-- /ET:INLINE -->
```

CSS 需显式声明 `type="css"`（或文件名 `.css` 亦可自动识别）：

```html
<!-- ET:INLINE name="et-import" src="ui/et-import.css" type="css" -->
<style>/* 自动注入 */</style>
<!-- /ET:INLINE -->
```

SVG：

```html
<!-- ET:INLINE name="icons" src="icons/icons.svg" type="svg" -->
<svg style="display:none"><!-- symbol 集合 --></svg>
<!-- /ET:INLINE -->
```

### 2. 在 `shared.manifest.json` 登记

```json
{
  "random-name-classic": {
    "random-pick": "logic/random-pick.js",
    "icons": "icons/icons.svg"
  }
}
```

> 登记是可选的（标记里的 `src` 已足够），但登记后**改路径只需改这一处**。

### 3. 同步

```bash
python scripts/sync_shared.py                # 全部
python scripts/sync_shared.py random-name-classic
python scripts/sync_shared.py --check        # CI / pre-commit
```

---

## 硬规则

| 规则 | 说明 |
|---|---|
| ✅ 改逻辑**只改这里** | 不在工具里改内联副本 |
| ❌ **禁止**手工编辑 `ET:INLINE` 之间的内容 | 下次同步会被覆盖 |
| ❌ **禁止**手工复制粘贴逻辑到工具 | 提交前 `--check` 会失败 |
| ✅ 片段必须**自包含** | 不互相 `require`，不引用外部文件 |
| ✅ 片段必须 **ES5 兼容** | 是 IIFE 或纯函数，不污染全局（或只挂一个命名空间） |
| ✅ 片段内**禁止**外部 URL | `check_no_external.py` 会扫这里 |

---

## 与网站的关系

**零关系。** 这里的图标与网站 `public/assets/icons/sprite.svg` 完全独立，
两者不共享、不互引。工具带去任何地方都能跑，不依赖本站。
