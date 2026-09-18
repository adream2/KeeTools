#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""准备图标离线源：按 `icons.txt` 从 Iconify 提取图标集子集。

**这是唯一的联网脚本**，只在「新增 / 变更图标清单」时手工跑一次；
产物 `assets-src/icons/<set>.json` 入库后，`build_sprite.py` 全程离线。

用法：
    python scripts/icons/prepare_source.py                 # 默认 lucide
    python scripts/icons/prepare_source.py --set tabler    # 指定图标集
    python scripts/icons/prepare_source.py --dry-run       # 只打印将拉取的图标名

设计说明：
- 只保留 Iconify 子集实际用到的字段，避免把整个图标库（数 MB）带进仓库；
- `aliases` **必须保留**：Lucide 中 `home` / `trash-2` 等只是别名，
  定义体挂在被指向的图标上，构建脚本依赖该字段回溯；
- 不覆盖已存在的源文件，除非显式 `--force`，防止误删手工增补的图标。
"""

from __future__ import annotations

import argparse
import json
import sys
import urllib.error
import urllib.request
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
ICONS_ROOT = ROOT / "assets-src" / "icons"
ICONS_TXT = ICONS_ROOT / "icons.txt"
API = "https://api.iconify.design/{set_name}.json?icons={names}"

# 只保留构建用得上的字段；lastModified 用于溯源，写入第三方许可证登记表
KEEP_FIELDS = ("prefix", "width", "height", "lastModified", "icons", "aliases")


def read_icon_names(set_name: str) -> list[str]:
    """读取 icons.txt，返回属于指定图标集的图标名（保持书写顺序、去重）。"""
    if not ICONS_TXT.is_file():
        sys.exit(f"[ERR]  未找到图标清单：{ICONS_TXT.relative_to(ROOT)}")

    names: list[str] = []
    for lineno, raw in enumerate(ICONS_TXT.read_text(encoding="utf-8").splitlines(), 1):
        line = raw.strip()
        if not line or line.startswith("#"):
            continue

        prefix, sep, short = line.partition(":")
        if not sep:
            sys.exit(
                f"[ERR]  icons.txt 第 {lineno} 行缺少图标集前缀：{line!r}\n"
                f"       正确写法：{set_name}:icon-name"
            )
        if prefix.strip().lower() != set_name.lower():
            continue

        short = short.strip()
        if short and short not in names:
            names.append(short)

    return names


def fetch_subset(set_name: str, names: list[str]) -> dict:
    """向 Iconify 拉取子集；网络错误给出可读提示而非堆栈。"""
    url = API.format(set_name=set_name, names=",".join(names))
    req = urllib.request.Request(url, headers={"User-Agent": "KeeTools-icon-source-prep"})
    try:
        with urllib.request.urlopen(req, timeout=60) as resp:
            data = json.loads(resp.read().decode("utf-8"))
    except urllib.error.HTTPError as exc:
        sys.exit(f"[ERR]  Iconify 返回 HTTP {exc.code}，请检查图标集名 {set_name!r} 是否存在")
    except (urllib.error.URLError, TimeoutError) as exc:
        sys.exit(f"[ERR]  无法访问 Iconify（本脚本需要联网）：{exc}")

    if not isinstance(data.get("icons"), dict) or not data["icons"]:
        sys.exit(f"[ERR]  Iconify 未返回任何图标，请核对 icons.txt 中的图标名")

    return {k: v for k, v in data.items() if k in KEEP_FIELDS}


def main() -> int:
    parser = argparse.ArgumentParser(description="从 Iconify 准备图标离线源")
    parser.add_argument("--set", dest="set_name", default="lucide", help="图标集名（默认 lucide）")
    parser.add_argument("--force", action="store_true", help="覆盖已存在的源文件")
    parser.add_argument("--dry-run", action="store_true", help="只打印将拉取的图标名，不联网")
    args = parser.parse_args()

    names = read_icon_names(args.set_name)
    if not names:
        sys.exit(f"[ERR]  icons.txt 中没有 {args.set_name}: 前缀的图标")

    print(f"[INFO] 图标集 {args.set_name}，共 {len(names)} 个图标")

    if args.dry_run:
        print("       " + ", ".join(names))
        print("[OK]   --dry-run，未联网")
        return 0

    out = ICONS_ROOT / f"{args.set_name}.json"
    if out.is_file() and not args.force:
        sys.exit(f"[ERR]  源文件已存在：{out.relative_to(ROOT)}（如需重建请加 --force）")

    subset = fetch_subset(args.set_name, names)
    out.parent.mkdir(parents=True, exist_ok=True)
    out.write_text(
        json.dumps(subset, ensure_ascii=False, indent=2, sort_keys=True) + "\n",
        encoding="utf-8",
        newline="\n",
    )

    icons = subset.get("icons", {})
    aliases = subset.get("aliases", {})
    missing = [n for n in names if n not in icons and n not in aliases]
    if missing:
        print(f"[WARN] 以下图标在源中不存在，构建时将报错：{', '.join(missing)}")

    print(
        f"[OK]   {len(icons)} icons + {len(aliases)} aliases "
        f"-> {out.relative_to(ROOT).as_posix()}"
    )
    print(f"       lastModified={subset.get('lastModified', '未知')}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
