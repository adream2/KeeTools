# 工程脚本

> 主体为 **Python 3 标准库**，不引入 pip 依赖；数据库初始化为 PHP（复用框架 `Database` 类）。
> 退出码约定：`0` 成功 / 通过，`1` 校验失败。

---

## 脚本清单

| 脚本 | 作用 | 何时跑 |
|---|---|---|
| [`init_db.php`](init_db.php) | **PHP 脚本**：建库建表（`storage/app.db` + `storage/stats.db`）+ 分类种子数据；幂等，支持 `--check`（只读校验）/ `--force`（删库重建） | 首次部署 / 表结构变更后 |
| [`sync_shared.py`](sync_shared.py) | 把 `tools/_shared/` 片段幂等内联进各工具 HTML | 改完共享逻辑后 / 提交前 `--check` |
| [`check_manifest.py`](check_manifest.py) | 校验 `manifest.json` 字段 + `ET-META` 一致性 + **体积守卫**（`TOOL_MAX_KB`，默认 500KB） | 新增或修改工具后 |
| [`check_no_external.py`](check_no_external.py) | 零外部依赖检查（禁止 CDN / 在线字体 / 在线图标） | 提交前必跑 |
| [`check_css_tokens.py`](check_css_tokens.py) | CSS 字面量检查（须用 `var(--...)` 令牌） | 改过 CSS 后 |
| [`build_css.py`](build_css.py) | 四层 CSS 拼合产出到 `public/assets/css/` | 改过 CSS 源后 / 提交前 `--check` |
| [`new_tool.py`](new_tool.py) | 新工具脚手架（模板取 `tools/_template/`，自动登记共享片段 + 内联 + 自检） | 新建工具时 |
| [`asset_report.py`](asset_report.py) | 静态资源体积审计（`public/` 汇总 + 各工具 HTML 体积排行，只读） | 每批工具完成后 / 发版前 |
| [`icons/prepare_source.py`](icons/prepare_source.py) | 从 Iconify 提取图标离线源（**唯一联网脚本**） | 改过 `icons.txt` 后（低频） |
| [`icons/build_sprite.py`](icons/build_sprite.py) | 网站图标 sprite 子集化（离线） | 改过 `icons.txt` 后 |

> `icons/build_sprite.py` 全程**不联网**：图标源为 `assets-src/icons/` 下的
> `svg/*.svg` 或 `<图标集>.json`（Iconify JSON 格式，已入库）。
> 支持 Iconify **别名回溯**（如 `lucide:home` → `house`、`lucide:trash-2` → `trash`）。
> 图标源准备方式见 [`../assets-src/icons/README.md`](../assets-src/icons/README.md)。
>
> `icons/prepare_source.py` 是**唯一需要联网**的脚本，只在新增图标时手工跑一次
> （`python scripts/icons/prepare_source.py`），产物入库后构建链完全离线。
> 它默认不覆盖已有源文件，加 `--force` 才重建，避免误删手工增补的图标。

---

## 常用组合

```bash
# 提交前全量校验（六项，与 pre-commit 一致）
python scripts/check_manifest.py
python scripts/check_no_external.py
python scripts/sync_shared.py --check
python scripts/check_css_tokens.py
python scripts/build_css.py --check
python scripts/icons/build_sprite.py --check

# 新建一个工具
python scripts/new_tool.py

# 改完共享逻辑 → 同步到所有工具
python scripts/sync_shared.py

# 图标清单变更 → 重建 sprite
python scripts/icons/build_sprite.py

# 体积审计（每批工具完成后）
python scripts/asset_report.py
```

---

## `--check` 模式

`sync_shared.py --check`、`build_css.py --check` 与 `icons/build_sprite.py --check` 用于 CI / pre-commit：

- 有差异 → 输出 `[FAIL]` 并返回退出码 `1`
- 无差异 → `[OK]` 并返回 `0`

---

## 降级行为（首次检出仓库时）

骨架阶段尚未建立对应内容时，脚本应**优雅跳过**而非报错：

| 情况 | 表现 |
|---|---|
| `tools/` 无工具目录 | `[OK] 没有可校验的工具` |
| `assets-src/css/` 无 CSS | `[OK] 没有需要检查的 CSS 文件` |
| 离线图标源未就绪 | `[WARN] 跳过构建`，退出码 `0` |
| `tools/_shared/` 不存在 | `[WARN] 跳过同步`，退出码 `0` |

> 这让"刚 clone 下来"和"P0 骨架阶段"都能直接跑通全部命令，不用先造数据。

---

## 编写新脚本的约定

1. 文件头写 docstring：**作用 / 用法 / 退出码**，并指向对应规范文档
2. `ROOT = Path(__file__).resolve().parent.parent`
3. 路径一律用 `pathlib.Path`，输出用 `relative_to(ROOT).as_posix()` 保持跨平台一致
4. 只读路径检查不抛异常，写文件前 `mkdir(parents=True, exist_ok=True)`
5. 写文本文件固定 `encoding="utf-8", newline="\n"`
6. 输出用 `[OK]` / `[WARN]` / `[ERR]` / `[FAIL]` / `[SYNC]` 前缀
7. 入口统一 `if __name__ == "__main__": sys.exit(main())`
8. 禁止写入 `var/` 之外的非计划文件（见 `AGENTS.md` §3.4）
