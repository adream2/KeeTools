# 工程脚本

> 全部 **Python 3 标准库**，不引入 pip 依赖。
> 退出码约定：`0` 成功 / 通过，`1` 校验失败。

---

## 脚本清单

| 脚本 | 作用 | 何时跑 |
|---|---|---|
| [`sync_shared.py`](sync_shared.py) | 把 `tools/_shared/` 片段幂等内联进各工具 HTML | 改完共享逻辑后 / 提交前 `--check` |
| [`check_manifest.py`](check_manifest.py) | 校验 `manifest.json` 字段 + `ET-META` 一致性 | 新增或修改工具后 |
| [`check_no_external.py`](check_no_external.py) | 零外部依赖检查（禁止 CDN / 在线字体 / 在线图标） | 提交前必跑 |
| [`check_css_tokens.py`](check_css_tokens.py) | CSS 字面量检查（须用 `var(--...)` 令牌） | 改过 CSS 后 |
| [`new_tool.py`](new_tool.py) | 新工具脚手架（生成 manifest / HTML / CHANGELOG） | 新建工具时 |
| [`icons/build_sprite.py`](icons/build_sprite.py) | 网站图标 sprite 子集化（离线） | 改过 `icons.txt` 后 |

---

## 常用组合

```bash
# 提交前全量校验（四项）
python scripts/check_manifest.py
python scripts/check_no_external.py
python scripts/sync_shared.py --check
python scripts/check_css_tokens.py

# 新建一个工具
python scripts/new_tool.py

# 改完共享逻辑 → 同步到所有工具
python scripts/sync_shared.py

# 图标清单变更 → 重建 sprite
python scripts/icons/build_sprite.py
```

---

## `--check` 模式

`sync_shared.py --check` 与 `icons/build_sprite.py --check` 用于 CI / pre-commit：

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
