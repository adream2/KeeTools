#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""静态资源体积审计（P4 §六）。

统计范围：
    public/   对外暴露的全部静态资源（CSS / JS / 图标 sprite 等）
    tools/    各工具 index.html 体积（体积守卫的手工复核入口）

用法：
    python scripts/asset_report.py            # 全部报告
    python scripts/asset_report.py --top 30   # 调整"最大文件"列表长度
    python scripts/asset_report.py --tools    # 只看工具体积排行

退出码：0 = 成功（本脚本只读，不参与门禁判定）

设计依据：AGENTS.md §七、docs/工具开发规范.md（体积红线 500KB / 工具）。
"""

import argparse
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
PUBLIC_DIR = ROOT / "public"
TOOLS_DIR = ROOT / "tools"

# 体积告警阈值（单工具 HTML，与 check_manifest.py 的 TOOL_MAX_KB 保持一致口径）
TOOL_WARN_KB = 500


def human(size: int) -> str:
    if size >= 1024 * 1024:
        return f"{size / 1024 / 1024:.2f} MB"
    return f"{size / 1024:.1f} KB"


def scan(directory: Path) -> list[tuple[Path, int]]:
    if not directory.is_dir():
        return []
    out: list[tuple[Path, int]] = []
    for path in sorted(directory.rglob("*")):
        if not path.is_file():
            continue
        try:
            out.append((path, path.stat().st_size))
        except OSError:
            continue
    return out


def report_group(title: str, files: list[tuple[Path, int]]) -> None:
    print(f"\n== {title} ==")
    if not files:
        print("[WARN] 无文件（目录不存在或为空）")
        return

    total = sum(size for _, size in files)
    by_dir: dict[str, list[int]] = {}
    by_suffix: dict[str, list[int]] = {}

    for path, size in files:
        rel_dir = path.parent.relative_to(ROOT).as_posix()
        by_dir.setdefault(rel_dir, []).append(size)
        by_suffix.setdefault(path.suffix.lower() or "(无扩展名)", []).append(size)

    print(f"合计 {len(files)} 个文件，{human(total)}")
    print("\n按目录：")
    for name, sizes in sorted(by_dir.items(), key=lambda kv: -sum(kv[1])):
        print(f"  {name:<34} {len(sizes):>4} 个  {human(sum(sizes)):>10}")

    print("\n按类型：")
    for name, sizes in sorted(by_suffix.items(), key=lambda kv: -sum(kv[1])):
        print(f"  {name:<34} {len(sizes):>4} 个  {human(sum(sizes)):>10}")


def main() -> int:
    parser = argparse.ArgumentParser(description="静态资源体积审计")
    parser.add_argument("--top", type=int, default=20, help="最大文件列表长度（默认 20）")
    parser.add_argument("--tools", action="store_true", help="只看工具 HTML 体积排行")
    args = parser.parse_args()

    print("KeeTools 静态资源体积审计")
    print(f"仓库根：{ROOT.as_posix()}")

    if not args.tools:
        report_group("public/（对外静态资源）", scan(PUBLIC_DIR))

    entries = [
        (path, size)
        for path, size in scan(TOOLS_DIR)
        if path.name == "index.html" and not path.parts[len(TOOLS_DIR.parts)].startswith("_")
    ]
    entries.sort(key=lambda item: -item[1])

    print("\n== tools/（各工具 index.html）==")
    if not entries:
        print("[WARN] tools/ 下没有工具")
    else:
        total = sum(size for _, size in entries)
        over = []
        for path, size in entries:
            rel = path.parent.relative_to(TOOLS_DIR).as_posix()
            kb = size / 1024
            flag = ""
            if kb > TOOL_WARN_KB:
                flag = "  <-- 超过 500KB，须登记 docs/工具例外清单.md"
                over.append(rel)
            print(f"  {rel:<30} {kb:>8.1f} KB{flag}")
        print(f"\n  合计 {len(entries)} 个工具，{human(total)}，平均 {human(total // len(entries))}")
        if over:
            print(f"[WARN] {len(over)} 个工具超过 500KB：" + "、".join(over))
        else:
            print("[OK]   全部工具在 500KB 体积红线内")

    if not args.tools:
        top = sorted(scan(PUBLIC_DIR), key=lambda item: -item[1])[: max(1, args.top)]
        print(f"\n== public/ 最大的 {len(top)} 个文件 ==")
        if not top:
            print("[WARN] 无文件")
        for path, size in top:
            print(f"  {path.relative_to(ROOT).as_posix():<52} {human(size):>10}")

    print("\n[OK]   审计完成（只读报告，不作为门禁）")
    return 0


if __name__ == "__main__":
    sys.exit(main())
