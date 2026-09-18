# manifest.json 规范

> `manifest.json` 是工具与网站之间的**唯一契约**。
> 配套：[`工具开发规范.md`](工具开发规范.md)、[`需求文档-v2.md`](需求文档-v2.md)
> 最后更新：2026-09-18

---

## 一、完整示例

```json
{
  "id": "random-name-classic",
  "title": "随机点名器（经典版）",
  "version": "1.2.0",
  "type": "shell",
  "description": "导入名单即可随机点名，支持去重、已点标记、加权抽取。适合课堂互动与大屏投影。",
  "author": "EduTools Team",
  "entry": "index.html",
  "single_file": true,
  "offline": true,
  "grade_range": ["1-12"],
  "subjects": ["通用"],
  "tags": ["点名", "课堂互动", "抽签", "大屏"],
  "family": "random-name",
  "license": "free",
  "dependencies": [],
  "screen": "large",
  "stats_enabled": true,
  "created_at": "2026-09-01",
  "updated_at": "2026-09-18"
}
```

---

## 二、字段说明

| 字段 | 类型 | 必填 | 校验规则 | 说明 |
|---|---|---|---|---|
| `id` | string | ✅ | `^[a-z0-9-]+$`，且**必须等于目录名** | 全局唯一，即 URL slug |
| `title` | string | ✅ | 非空，≤ 40 字符 | 展示名称 |
| `version` | string | ✅ | `^\d+\.\d+\.\d+$` | 语义化版本 |
| `type` | enum | ✅ | `fixed` / `shell` / `experiment` | 工具类型 |
| `description` | string | ✅ | 非空，建议 ≤ 60 字 | 一句话简介，用于列表与 SEO |
| `author` | string | ✅ | 非空 | 作者 / 团队 |
| `entry` | string | ✅ | 必须为 `index.html` | 入口文件名 |
| `single_file` | bool | ✅ | — | 是否单文件。为 `false` 需登记例外清单 |
| `offline` | bool | ✅ | — | 是否离线可用 |
| `grade_range` | string[] | ✅ | 非空数组 | 适用学段，见 §3.1 |
| `subjects` | string[] | ✅ | 非空数组 | 学科，通用类填 `["通用"]` |
| `tags` | string[] | ✅ | 非空数组 | 自由标签，**关联同一逻辑不同花样工具的主要手段** |
| `family` | string | ❌ | `^[a-z0-9-]+$` | 逻辑族标识，同一算法的不同花样填相同值 |
| `license` | string | ❌ | 默认 `free` | 许可证 |
| `dependencies` | string[] | ✅ | 单文件工具**必须为空数组** | 本地资源依赖列表 |
| `screen` | enum | ❌ | `large`（默认）/ `any` | 目标屏幕 |
| `stats_enabled` | bool | ❌ | 默认 `true` | 是否纳入统计 |
| `created_at` | date | ✅ | `YYYY-MM-DD` | 创建日期 |
| `updated_at` | date | ✅ | `YYYY-MM-DD`，≥ `created_at` | 最后更新日期 |

---

## 三、字段取值规范

### 3.1 `grade_range`（学段）

| 值 | 含义 |
|---|---|
| `"1-2"` | 小学低年级 |
| `"3-4"` | 小学中年级 |
| `"5-6"` | 小学高年级 |
| `"1-6"` | 小学全段 |
| `"7-9"` | 初中 |
| `"10-12"` | 高中 |
| `"1-12"` | 全学段（通用工具） |

多学段用数组：`["1-6", "7-9"]`

### 3.2 `subjects`（学科）

| 分类 | 取值 |
|---|---|
| 通用 | `"通用"` |
| 语文 | `"语文"` |
| 数学 | `"数学"` |
| 英语 | `"英语"` |
| 物理 | `"物理"` |
| 化学 | `"化学"` |
| 生物 | `"生物"` |
| 政治 | `"政治"` |
| 历史 | `"历史"` |
| 地理 | `"地理"` |
| 科学 | `"科学"` |
| 信息 | `"信息技术"` |
| 体育 | `"体育"` |
| 音乐 | `"音乐"` |
| 美术 | `"美术"` |

多学科用数组：`["物理", "化学"]`

### 3.3 `type`（类型）

| 值 | 说明 | 示例 |
|---|---|---|
| `fixed` | 固定知识型，知识有限、标准 | 拼音表、元素周期表 |
| `shell` | 空壳容器型，老师自行导入数据 | 随机点名、大转盘、倒计时 |
| `experiment` | 实验模拟型，H5 理科实验交互 | 电路搭建、凸透镜成像 |

> **`fixed` 型数据边界**：内置数据 > 500 条时，应改为"通用渲染器 + 可替换数据文件"结构。

### 3.4 `family`（逻辑族）

用于把"同一逻辑的不同花样"关联起来。

示例：

```
random-name-classic    family: "random-name"   tags: ["点名", ...]
random-name-cartoon    family: "random-name"   tags: ["点名", ...]
random-name-neon       family: "random-name"   tags: ["点名", ...]
```

- 这三个是**三个独立工具**（各自目录、各自版本号、各自 CHANGELOG）
- 前台"相关工具"推荐区按 `family` 关联展示
- `family` 不参与 URL，仅用于内部分组与推荐

### 3.5 `screen`（目标屏幕）

| 值 | 说明 |
|---|---|
| `large` | 大屏 / 投影（默认） |
| `any` | 通用 |

---

## 四、`ET-META` 元数据块

工具的 `index.html` 中**必须**包含机器可读块，扫描器会与 `manifest.json` 比对。

```html
<!-- ET-META
{"id":"random-name-classic","version":"1.2.0"}
-->
```

**位置**：建议放在 `<head>` 内的 `<title>` 之后。

**要求**：

- `id` 必须与 manifest 一致
- `version` 必须与 manifest 一致
- JSON 必须单行可解析

**不一致时**：后台"工具管理"列表**必须高亮报错**，不允许静默通过。

---

## 五、校验脚本

```bash
python scripts/check_manifest.py
```

校验内容：

1. 遍历 `tools/*/manifest.json`（跳过 `_shared/`）
2. JSON 语法合法
3. 所有必填字段存在
4. 字段类型与格式符合 §2 规则
5. `id` 等于目录名
6. `entry` 文件存在
7. `single_file: true` 时 `dependencies` 为空
8. `index.html` 中 `ET-META` 存在且 `id` / `version` 与 manifest 一致
9. `updated_at` ≥ `created_at`
10. `tools/{id}/CHANGELOG.md` 存在

**退出码**：`0` 全部通过，`1` 有错误。

**输出示例**：

```
[OK]   random-name-classic        v1.2.0
[WARN] countdown-classic          v1.0.0  ET-META version 不一致 (manifest: 1.0.0, html: 1.0.1)
[ERR]  balloon-pop                v1.0.0  缺少必填字段: tags
[ERR]  scoreboard                 v1.1.0  id 与目录名不一致 (id: score-board, dir: scoreboard)

3 项错误，1 项警告
```

---

## 六、manifest 回写规则

后台编辑工具元数据时：

| 规则 | 说明 |
|---|---|
| **写回文件** | 修改直接落盘到 `tools/{id}/manifest.json` |
| 格式化 | `JSON_PRETTY_PRINT \| JSON_UNESCAPED_UNICODE \| JSON_UNESCAPED_SLASHES` |
| 缩进 | 2 空格 |
| 换行 | LF |
| 末尾 | 保留一个空行 |
| 顺序 | 保持字段顺序稳定（按 §2 表格顺序输出） |
| 权限 | 若 `tools/` 不可写 → 降级写 DB override 表，后台提示降级模式 |

**为什么写回文件**：避免"库里的描述和文件里的描述不一致"。改动就在文件里，整包拷走即可完整迁移，且可 git 版本化。

---

## 七、新建工具清单

```bash
# 1. 创建目录
mkdir -p tools/my-tool

# 2. 创建 manifest.json（参考 §1）
#    注意 id 必须等于目录名 my-tool

# 3. 创建 index.html（含 ET-META）
#    参考 docs/工具开发规范.md §10.2

# 4. 创建 CHANGELOG.md（初版 1.0.0）

# 5. 校验
python scripts/check_manifest.py

# 6. 在后台点"扫描同步"，或等定时任务

# 7. 提交
git add tools/my-tool
git commit -m "tool(my-tool): v1.0.0 初始版本"
```

---

## 八、常见错误

| 错误 | 现象 | 修正 |
|---|---|---|
| `id` 与目录名不一致 | 扫描报错，URL 404 | 改成一致 |
| `ET-META` 版本未同步 | 后台高亮报错 | 同步更新 |
| `single_file: true` 但 `dependencies` 非空 | 校验失败 | 改 `single_file: false` 并登记例外 |
| `grade_range` 用中文 | 前台筛选失效 | 用 `"1-6"` 等标准值 |
| `subjects` 写成 `"物理课"` | 分类匹配失败 | 用 `"物理"` |
| 忘记写 `CHANGELOG.md` | 校验失败 | 补上 |
| `updated_at` 早于 `created_at` | 校验失败 | 修正日期 |
| JSON 有尾随逗号 | 解析失败 | 删除尾随逗号 |
