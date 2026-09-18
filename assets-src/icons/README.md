# 网站图标源（离线）

> ⚠️ 本目录下的图标源**一律离线保存**，构建过程**不联网**。
> 构建命令：`python scripts/icons/build_sprite.py`

---

## 两个文件 / 目录的分工

| 路径 | 作用 |
|---|---|
| `icons.txt` | **手写**：本项目用到的图标名清单（每行一个） |
| `svg/*.svg` | 离线源 A：单个 SVG 文件（文件名 = 图标名） |
| `<图标集>.json` | 离线源 B：Iconify JSON 图标集（**推荐**） |
| `../../public/assets/icons/sprite.svg` | **产出**（自动生成，勿手工改） |

脚本优先读 `svg/`，读不到再从 `.json` 集合里找。

---

## 图标名写法

```
lucide:search          ← 推荐：带图标集前缀
tabler:brand-wechat
search                 ← 允许：不带前缀（此时只查 svg/search.svg）
```

产出 symbol id 可预测：`lucide:grid-2x2` → `#i-grid-2x2`

---

## 准备离线图标源

### 方式 A：Iconify JSON 集合（推荐）

1. 从 Iconify 的图标集仓库离线获取整个集合的 JSON，例如 Lucide 的 `lucide.json`
2. 放到本目录，命名 `<图标集名>.json`
3. 格式（Iconify 标准格式，只用到 `icons` / `width` / `height`）：

```json
{
  "prefix": "lucide",
  "width": 24,
  "height": 24,
  "icons": {
    "search": { "body": "<circle cx=\"11\" cy=\"11\" r=\"8\"/><path d=\"m21 21-4.3-4.3\"/>" },
    "download": { "body": "<path d=\"M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4\"/><polyline points=\"7 10 12 15 17 10\"/><line x1=\"12\" y1=\"15\" x2=\"12\" y2=\"3\"/>" }
  }
}
```

> 只需 `icons` 字段即可，`aliases` / `chars` 等未使用字段可保留或删除。

### 方式 B：单个 SVG 文件

把每个图标保存为 `svg/<图标名>.svg`，例如 `svg/search.svg`：

```xml
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
     stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
  <circle cx="11" cy="11" r="8"/>
  <path d="m21 21-4.3-4.3"/>
</svg>
```

脚本会：

- 提取 `viewBox`（缺失则默认 `0 0 24 24`）
- **移除所有硬编码 `fill` / `stroke` 颜色值**，让图标跟随 `currentColor`

---

## 选用图标的许可证要求

只允许**宽松许可**的图标集：

| 图标集 | 许可证 | 商用 |
|---|---|---|
| Lucide | ISC | ✅ |
| Tabler | MIT | ✅ |
| Phosphor | MIT | ✅ |
| Material Symbols | Apache-2.0 | ✅（保留声明） |

❌ **禁用**：`logos:` / `simple-icons:`（商标限制风险）

新增图标集时，必须同步登记到 [`../../THIRD-PARTY-LICENSES.md`](../../THIRD-PARTY-LICENSES.md)。

---

## 页面里的用法

```html
<svg class="icon" width="20" height="20" aria-hidden="true">
  <use href="/assets/icons/sprite.svg#i-search"></use>
</svg>
```

- 图标跟随文字颜色（`currentColor`），无需为暗色模式单独导出
- 不使用 icon font（字体加载失败会变方框）
- **工具内的图标完全独立**，见 [`../../tools/_shared/icons/icons.svg`](../../tools/_shared/icons/icons.svg)

---

## 工作流

```bash
# 1. 在 icons.txt 里加一行（如 lucide:filter）
# 2. 确认离线图标源里有这个图标
# 3. 构建
python scripts/icons/build_sprite.py

# 4. 校验产物是否为最新（CI 用）
python scripts/icons/build_sprite.py --check
```
