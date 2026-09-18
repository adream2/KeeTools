#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""CSS 构建脚本。

把 assets-src/css/ 下的源文件拷贝/拼接到 public/assets/css/。
自写 CSS 无需编译，本脚本只做「拼合 + 产出」，保证源与产物分离。

产出策略：
    - tokens.css / base.css / components.css / site.css 分别产出同名文件
    - 另产出 site.bundle.css（四层按顺序拼合，供前台一次加载）

用法：
    python scripts/build_css.py            # 构建
    python scripts/build_css.py --check    # 校验产物是否为最新（CI / pre-commit）

退出码：0 = 成功 / 无差异，1 = 失败 / 有差异

规范见 AGENTS.md §3.4、docs/架构说明.md。
"""

import argparse
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
SRC_DIR = ROOT / "assets-src" / "css"
OUT_DIR = ROOT / "public" / "assets" / "css"

# 按层顺序拼合（顺序即级联优先级）
LAYERS = ["tokens.css", "base.css", "components.css", "site.css"]
BUNDLE_NAME = "site.bundle.css"

BANNER = "/* 此文件由 scripts/build_css.py 自动生成，请勿手工编辑。源文件：assets-src/css/ */\n"


def build_bundle() -> str:
    """按层顺序拼合四层 CSS。"""
    parts = [BANNER]
    for name in LAYERS:
        path = SRC_DIR / name
        if not path.exists():
            continue
        parts.append(f"\n/* ════════ {name} ════════ */\n")
        parts.append(path.read_text(encoding="utf-8"))
    return "".join(parts)


def main() -> int:
    parser = argparse.ArgumentParser(description="构建 CSS 产出到 public/assets/css/")
    parser.add_argument("--check", action="store_true", help="只校验产物是否为最新，不写文件")
    args = parser.parse_args()

    if not SRC_DIR.exists():
        print("[WARN] CSS 源目录不存在，跳过构建。")
        return 0

    sources = [SRC_DIR / name for name in LAYERS if (SRC_DIR / name).exists()]
    if not sources:
        print("[WARN] 未找到可构建的 CSS 源文件，跳过构建。")
        return 0

    # 目标产物：四层同名文件 + bundle
    targets: dict[Path, str] = {}
    for src in sources:
        targets[OUT_DIR / src.name] = src.read_text(encoding="utf-8")
    targets[OUT_DIR / BUNDLE_NAME] = build_bundle()

    if args.check:
        stale = []
        for path, content in targets.items():
            if not path.exists() or path.read_text(encoding="utf-8") != content:
                stale.append(path)
        if stale:
            for path in stale:
                print(f"[FAIL] 产物不是最新：{path.relative_to(ROOT).as_posix()}")
            print("       请运行：python scripts/build_css.py")
            return 1
        print(f"[OK]   CSS 产物已是最新（{len(targets)} 个文件）")
        return 0

    OUT_DIR.mkdir(parents=True, exist_ok=True)
    # 用 newline="\n" 固定 LF，避免 Windows 下产出 CRLF
    for path, content in targets.items():
        path.write_text(content, encoding="utf-8", newline="\n")
        print(f"[SYNC] {path.relative_to(ROOT).as_posix()}")

    print(f"[OK]   CSS 构建完成（{len(targets)} 个文件）")
    return 0


if __name__ == "__main__":
    sys.exit(main())
