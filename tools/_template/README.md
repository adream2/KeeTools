# `tools/_template/` — 工具开发模板

> 以 `_` 开头的目录**不会被扫描**，也不会出现在前台与离线包中。

**不要直接复制这个目录**。请用脚手架生成：

```bash
python scripts/new_tool.py
```

脚手架会自动：

1. 校验 id 格式（`^[a-z0-9-]+$`），确保 id 等于目录名
2. 生成 `manifest.json`（字段顺序稳定，2 空格缩进，LF）
3. 生成 `index.html`（含 `ET-META` 块与 ES5 骨架）
4. 生成 `CHANGELOG.md`（初版 1.0.0）
5. 自动跑 `check_manifest.py` 与 `check_no_external.py`

带参数的非交互模式：

```bash
python scripts/new_tool.py --id random-name-classic --title "随机点名器（经典版）" \
    --type shell --subjects 通用 --grades 1-12 --yes
```

---

## 开发前后必读

| 内容 | 位置 |
|---|---|
| 工具开发规范（必读） | [`../../docs/工具开发规范.md`](../../docs/工具开发规范.md) |
| manifest 字段定义 | [`../../docs/manifest规范.md`](../../docs/manifest规范.md) |
| 编码规范 | [`../../docs/编码规范.md`](../../docs/编码规范.md) |
| 共享逻辑用法 | [`../_shared/README.md`](../_shared/README.md) |

---

## 提交前自检

```bash
python scripts/check_manifest.py tools/<id>
python scripts/check_no_external.py
python scripts/sync_shared.py --check
```

手动验证：

- [ ] 双击 `index.html`（`file://`）可用
- [ ] 断网可用，DevTools Network 无任何外部请求
- [ ] 1024×768 与 1920×1080 下都正常
- [ ] 无 `?.` / `??` / `structuredClone` / `:has()`
- [ ] `localStorage` key 用 `et_{toolId}_` 前缀
