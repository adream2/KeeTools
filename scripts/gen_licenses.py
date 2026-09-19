#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""THIRD-PARTY-LICENSES.md 生成器。

唯一真源：assets-src/third-party.json。
用法：
    python scripts/gen_licenses.py            # 生成 / 覆盖 THIRD-PARTY-LICENSES.md
    python scripts/gen_licenses.py --check    # 校验产物是否为最新（CI / pre-commit）

新增第三方素材时：只改 third-party.json → 跑本脚本 → 提交两个文件。
退出码：0 = 成功 / 已最新，1 = 失败 / 需要更新。
"""

import json
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
SOURCE = ROOT / "assets-src" / "third-party.json"
TARGET = ROOT / "THIRD-PARTY-LICENSES.md"

PREAMBLE = """# 第三方资源许可证登记

> 本文件由 `scripts/gen_licenses.py` 从 **`assets-src/third-party.json`** 自动生成，
> **请勿手工编辑**（登记 / 修改请改 JSON 后重新生成）。
> 前台展示版见 `/about`（同样由 JSON 自动渲染）。

---

## 登记规则

新增任何第三方资源（图标 / 音频 / 数据 / 字体 / JS 库）时，必须：

{rules}

**禁令**：

{forbidden}

---
"""


def build_markdown(data: dict) -> str:
    lines: list[str] = [PREAMBLE.replace("{rules}", "")]

    # 登记规则 / 禁令（编号列表）
    rules = data.get("rules", [])
    rules_md = "\n".join(f"{i}. {r}" for i, r in enumerate(rules, 1))
    forbidden = data.get("forbidden", [])
    forbidden_md = "\n".join(f"- ❌ {r}" for r in forbidden)
    text = PREAMBLE.replace("{rules}", rules_md).replace("{forbidden}", forbidden_md)
    lines = [text]

    for cat in data.get("categories", []):
        lines.append(f"\n## {cat.get('title', '')}\n")
        items = cat.get("items", [])
        if items:
            lines.append("| 资源 | 来源 | 许可证 | 商用 | 署名要求 | 用途 |")
            lines.append("|---|---|---|---|---|---|")
            for it in items:
                commercial = "✅" if it.get("commercial", True) else "⚠️"
                name = it["name"].replace("|", "\\|")
                source = it.get("source", "").replace("|", "\\|")
                usage = it.get("usage", "").replace("|", "\\|")
                lines.append(
                    f"| {name} | {source} | {it.get('license', '')} "
                    f"| {commercial} | {it.get('attribution', '—')} | {usage} |"
                )
        for note in cat.get("notes", []):
            lines.append(f"\n> {note}")
        lines.append("\n---\n")

    audit = data.get("audit", [])
    if audit:
        lines.append("\n## 审计记录\n")
        lines.append("| 日期 | 操作 | 说明 |")
        lines.append("|---|---|---|")
        for a in audit:
            lines.append(f"| {a.get('date', '')} | {a.get('action', '')} | {a.get('detail', '')} |")

    return "\n".join(lines).rstrip() + "\n"


def main() -> int:
    try:
        data = json.loads(SOURCE.read_text(encoding="utf-8"))
    except (OSError, json.JSONDecodeError) as exc:
        print(f"[ERR] 读取 {SOURCE} 失败：{exc}")
        return 1

    markdown = build_markdown(data)

    if "--check" in sys.argv:
        current = TARGET.read_text(encoding="utf-8") if TARGET.exists() else ""
        if current == markdown:
            print("[OK]   THIRD-PARTY-LICENSES.md 已是最新（由 third-party.json 生成）")
            return 0
        print("[FAIL] THIRD-PARTY-LICENSES.md 与 third-party.json 不同步")
        print("       请运行：python scripts/gen_licenses.py")
        return 1

    TARGET.write_text(markdown, encoding="utf-8", newline="\n")
    count = sum(len(c.get("items", [])) for c in data.get("categories", []))
    print(f"[OK]   THIRD-PARTY-LICENSES.md 已生成（{count} 条登记）")
    return 0


if __name__ == "__main__":
    sys.exit(main())
