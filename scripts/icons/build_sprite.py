#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""图标 sprite 构建脚本（网站用，**全程不联网**）。

数据流：

    assets-src/icons/icons.txt        # 手写：用到的图标名清单（每行一个，如 lucide:search）
    assets-src/icons/svg/*.svg        # 离线源 A：单个 SVG 文件
    assets-src/icons/{set}.json       # 离线源 B：Iconify JSON 图标集（推荐）
            ↓
    public/assets/icons/sprite.svg    # 产出：SVG sprite（仅含用到的图标）

产出格式：

    <svg xmlns="..." style="display:none">
      <symbol id="i-search" viewBox="0 0 24 24">…</symbol>
    </svg>

页面用法：

    <svg class="icon" width="20" height="20"><use href="/assets/icons/sprite.svg#i-search"></use></svg>

图标源获取见 assets-src/icons/README.md。设计说明见 docs/图标与许可证.md。

用法：
    python scripts/icons/build_sprite.py
    python scripts/icons/build_sprite.py --check     # 只校验产物是否为最新

退出码：0 = 成功或图标源尚未就绪，1 = 失败（源部分缺失 / 产物过期）
"""

import argparse
import json
import re
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent.parent
SRC_DIR = ROOT / "assets-src" / "icons"
SVG_SRC_DIR = SRC_DIR / "svg"
ICONS_TXT = SRC_DIR / "icons.txt"
OUT_FILE = ROOT / "public" / "assets" / "icons" / "sprite.svg"

SVG_TAG_RE = re.compile(r"<svg\b([^>]*)>(.*)</svg\s*>", re.DOTALL | re.IGNORECASE)
VIEWBOX_RE = re.compile(r'viewBox\s*=\s*"([^"]+)"', re.IGNORECASE)
# 去掉硬编码颜色，改用 currentColor 跟随主题。
# 保留 none / currentColor / inherit：它们不是硬编码颜色，删掉反而会让图标失去描边。
HARD_COLOR_RE = re.compile(
    r'\s(fill|stroke)\s*=\s*"(?!(?:none|currentColor|inherit)\s*")[^"]*"',
    re.IGNORECASE,
)

# Iconify 别名链最大回溯深度，防御异常源文件造成的死循环
MAX_ALIAS_DEPTH = 8

BANNER = (
    "<!-- 此文件由 scripts/icons/build_sprite.py 自动生成，请勿手工修改 -->\n"
    "<!-- 源：assets-src/icons/icons.txt + assets-src/icons/ 下的离线图标源 -->\n"
)

DEFAULT_BOX = "0 0 24 24"


def read_icon_list() -> list[str]:
    """读取 icons.txt，忽略空行与 # 注释，保持顺序去重。"""
    if not ICONS_TXT.exists():
        return []
    names: list[str] = []
    for line in ICONS_TXT.read_text(encoding="utf-8").splitlines():
        name = line.strip()
        if not name or name.startswith("#"):
            continue
        if name not in names:
            names.append(name)
    return names


def split_name(icon_name: str) -> tuple[str, str]:
    """`lucide:search` → ("lucide", "search")；`search` → ("", "search")。"""
    if ":" in icon_name:
        set_name, short = icon_name.split(":", 1)
        return set_name.strip(), short.strip()
    return "", icon_name.strip()


def symbol_id(icon_name: str) -> str:
    """`lucide:grid-2x2` → `i-grid-2x2`。"""
    _, short = split_name(icon_name)
    short = re.sub(r"[^a-zA-Z0-9-]", "-", short).strip("-").lower()
    return f"i-{short}"


class IconSource:
    """离线图标源：优先单文件 SVG，其次 Iconify JSON 集合。"""

    def __init__(self) -> None:
        self.json_cache: dict[str, dict] = {}

    def json_sets(self) -> list[Path]:
        return sorted(SRC_DIR.glob("*.json"))

    def load_json_set(self, path: Path) -> dict:
        key = str(path)
        if key in self.json_cache:
            return self.json_cache[key]
        try:
            with path.open("r", encoding="utf-8") as fh:
                data = json.load(fh)
        except (json.JSONDecodeError, OSError):
            data = {}
        self.json_cache[key] = data if isinstance(data, dict) else {}
        return self.json_cache[key]

    def from_json(self, set_name: str, short: str) -> tuple[str | None, str]:
        """从本地 Iconify JSON 集合中取图标 body（支持别名回溯）。"""
        for path in self.json_sets():
            if set_name and path.stem.lower() != set_name.lower():
                continue
            data = self.load_json_set(path)
            icons = data.get("icons")
            if not isinstance(icons, dict):
                continue
            entry = self.resolve_icon(data, icons, short)
            if entry is None:
                continue
            body = entry.get("body")
            if not isinstance(body, str):
                continue
            width = data.get("width", 24)
            height = data.get("height", 24)
            box = f"0 0 {width} {height}"
            return body, box
        return None, ""

    @staticmethod
    def resolve_icon(data: dict, icons: dict, short: str) -> dict | None:
        """解析图标定义，沿 `aliases` 的 parent 链回溯到真实图标。

        Lucide 中 `home` / `trash-2` / `alert-triangle` / `globe-2` 等只是别名，
        定义体挂在被指向的图标上（`house` / `trash` / `triangle-alert` / `earth`）。
        """
        aliases = data.get("aliases")
        if not isinstance(aliases, dict):
            aliases = {}

        name = short
        for _ in range(MAX_ALIAS_DEPTH):
            entry = icons.get(name)
            if isinstance(entry, dict) and isinstance(entry.get("body"), str):
                return entry

            alias = aliases.get(name)
            if not isinstance(alias, dict):
                # 也可能是带 body 的别名条目
                if isinstance(entry, dict):
                    return entry
                return None

            parent = alias.get("parent")
            if not isinstance(parent, str) or not parent or parent == name:
                return None
            name = parent

        return None

    def from_svg_file(self, short: str) -> tuple[str | None, str]:
        path = SVG_SRC_DIR / f"{short}.svg"
        if not path.exists():
            return None, ""
        raw = path.read_text(encoding="utf-8", errors="replace").strip()
        match = SVG_TAG_RE.search(raw)
        if not match:
            return None, ""
        attrs, body = match.group(1), match.group(2)
        vb_match = VIEWBOX_RE.search(attrs)
        return body, (vb_match.group(1) if vb_match else DEFAULT_BOX)

    def lookup(self, icon_name: str) -> tuple[str | None, str, str]:
        """返回 (body, viewBox, 错误信息)。"""
        set_name, short = split_name(icon_name)
        if not short:
            return None, "", "图标名为空"

        body, box = self.from_svg_file(short)
        if body is not None:
            return body, box, ""

        body, box = self.from_json(set_name, short)
        if body is not None:
            return body, box, ""

        if set_name:
            return None, "", f"离线源中找不到 {icon_name}（svg/{short}.svg 或 {set_name}.json）"
        return None, "", f"离线源中找不到 {icon_name}（svg/{short}.svg）"

    def has_any_source(self) -> bool:
        if SVG_SRC_DIR.exists() and any(SVG_SRC_DIR.glob("*.svg")):
            return True
        return any(self.json_sets())


def build_symbol(source: IconSource, icon_name: str) -> tuple[str | None, str]:
    body, view_box, err = source.lookup(icon_name)
    if err:
        return None, err
    body = HARD_COLOR_RE.sub("", body).strip()
    sid = symbol_id(icon_name)
    symbol = (
        f'  <symbol id="{sid}" viewBox="{view_box}">\n'
        f"    {body}\n"
        f"  </symbol>"
    )
    return symbol, ""


def render(symbols: list[str]) -> str:
    return (
        BANNER
        + '<svg xmlns="http://www.w3.org/2000/svg" style="display:none" aria-hidden="true">\n'
        + "\n".join(symbols)
        + "\n</svg>\n"
    )


def main() -> int:
    parser = argparse.ArgumentParser(description="构建网站图标 sprite（离线）")
    parser.add_argument("--check", action="store_true", help="只校验产物是否为最新")
    args = parser.parse_args()

    icons = read_icon_list()

    # ── 图标源未就绪：跳过（P0 骨架阶段属正常）────
    source = IconSource()
    if not source.has_any_source():
        if args.check:
            if not icons:
                print("[OK]   icons.txt 为空，无需构建。")
                return 0
            print("[WARN] 离线图标源尚未就绪，sprite 未构建。")
            print("       见 assets-src/icons/README.md 准备离线图标源。")
            return 0
        print(f"[WARN] 离线图标源尚未就绪（{SVG_SRC_DIR.relative_to(ROOT).as_posix()}/ 与 *.json 均为空）")
        print("       跳过构建；准备图标源后重跑本脚本。见 assets-src/icons/README.md")
        return 0

    if not icons:
        print("[WARN] icons.txt 为空或不存在，无需构建。")
        return 0

    symbols: list[str] = []
    errors: list[str] = []

    for icon in icons:
        symbol, err = build_symbol(source, icon)
        if err:
            errors.append(f"[ERR]  {err}")
        else:
            symbols.append(symbol)

    for line in errors:
        print(line)

    if errors and not symbols:
        print("\n[FAIL] 没有任何图标可构建，请补齐离线图标源。")
        return 1

    content = render(symbols)

    if args.check:
        if OUT_FILE.exists() and OUT_FILE.read_text(encoding="utf-8") == content:
            print(f"[OK]   sprite 已是最新（{len(symbols)} 个图标）")
            return 0 if not errors else 1
        print("[FAIL] sprite 未构建或已过期，请运行：python scripts/icons/build_sprite.py")
        return 1

    OUT_FILE.parent.mkdir(parents=True, exist_ok=True)
    OUT_FILE.write_text(content, encoding="utf-8", newline="\n")

    print(f"[OK]   构建 {len(symbols)} 个图标 → {OUT_FILE.relative_to(ROOT).as_posix()}")
    if errors:
        print(f"       跳过 {len(errors)} 个图标（离线源缺失）")
    return 0 if not errors else 1


if __name__ == "__main__":
    sys.exit(main())
