#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""CSS 设计令牌检查脚本。

检查 base.css / components.css / site.css 是否使用了字面量颜色与尺寸，
要求一律使用 var(--...)。

tokens.css 是令牌定义文件，**豁免**检查。
工具内 CSS 自包含，不受约束（不在扫描范围）。

用法：
    python scripts/check_css_tokens.py             # 扫描 assets-src/css
    python scripts/check_css_tokens.py <文件>       # 扫描指定文件

退出码：0 = 通过，1 = 发现字面量

规则见 AGENTS.md §6.4、docs/需求文档-v2.md §7.5。
"""

import argparse
import re
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
CSS_DIR = ROOT / "assets-src" / "css"

# 豁免文件（令牌定义本身）
EXEMPT_FILES = {"tokens.css"}

# 受约束文件（只有这三层禁止字面量）
GOVERNED_FILES = {"base.css", "components.css", "site.css"}

# 字面量检测规则
LITERAL_COLOR_RE = re.compile(
    r"(#[0-9a-fA-F]{3,8}\b)"
    r"|(\brgba?\s*\()"
    r"|(\bhsla?\s*\()"
)
NAMED_COLOR_RE = re.compile(
    r":\s*(white|black|red|blue|green|yellow|orange|purple|gray|grey|pink|brown|silver|gold|navy|teal|olive|lime|aqua|maroon|fuchsia)\s*[;!]",
    re.IGNORECASE,
)

# 字面量尺寸：px / rem / em（0 与百分比、vh/vw 允许）
LITERAL_SIZE_RE = re.compile(r"(?<![-\w.])(\d+(?:\.\d+)?)(px|rem|em)\b")

# 允许字面量的属性（结构性，非设计令牌）
SIZE_ALLOWED_PROPS = (
    "z-index",
    "font-weight",
    "line-height",
    "opacity",
    "flex",
    "order",
    "grid-column",
    "grid-row",
    "aspect-ratio",
    "transform",
    "border-width: 0",
)

# 行内豁免标记
EXEMPT_MARKER = "et-allow-literal"

# 允许在边框/描边等场景使用 0 和 1px 的单像素技术线
SIZE_WHITELIST_VALUES = {"0px", "1px", "0rem", "0em", "2px"}


class Finding:
    def __init__(self, path: Path, line_no: int, kind: str, value: str, line: str) -> None:
        self.path = path
        self.line_no = line_no
        self.kind = kind
        self.value = value
        self.line = line.strip()

    def report(self) -> str:
        rel = self.path.relative_to(ROOT).as_posix()
        snippet = self.line if len(self.line) <= 100 else self.line[:97] + "..."
        return f"[ERR]  {rel}:{self.line_no}  {self.kind} 字面量 {self.value}\n         {snippet}"


def strip_comments(text: str) -> list[tuple[int, str]]:
    """去掉 /* */ 注释，返回 (行号, 行内容) 列表。"""
    lines = text.splitlines()
    result: list[tuple[int, str]] = []
    in_comment = False
    for idx, line in enumerate(lines, start=1):
        out = []
        i = 0
        while i < len(line):
            if in_comment:
                end = line.find("*/", i)
                if end == -1:
                    i = len(line)
                else:
                    in_comment = False
                    i = end + 2
                continue
            start = line.find("/*", i)
            if start == -1:
                out.append(line[i:])
                break
            out.append(line[i:start])
            in_comment = True
            i = start + 2
        result.append((idx, "".join(out)))
    return result


def scan_file(path: Path) -> list[Finding]:
    findings: list[Finding] = []
    try:
        text = path.read_text(encoding="utf-8", errors="replace")
    except OSError:
        return findings

    # 豁免标记写在注释里，必须在剥离注释前用原始行判断，
    # 否则注释被去掉后标记永远匹配不到，逃生阀失效。
    raw_lines = text.splitlines()

    for line_no, line in strip_comments(text):
        raw = raw_lines[line_no - 1] if line_no - 1 < len(raw_lines) else ""
        if EXEMPT_MARKER in raw or EXEMPT_MARKER in line:
            continue
        stripped = line.strip()
        if not stripped:
            continue

        # 媒体查询 / 特性查询的断点数值无法用 var()（CSS 规范限制），豁免整行
        if stripped.startswith("@media") or stripped.startswith("@supports"):
            continue

        # 颜色字面量
        for match in LITERAL_COLOR_RE.finditer(line):
            findings.append(Finding(path, line_no, "颜色", match.group(0), line))
        for match in NAMED_COLOR_RE.finditer(line):
            findings.append(Finding(path, line_no, "颜色", match.group(1), line))

        # 尺寸字面量
        if any(prop in line for prop in SIZE_ALLOWED_PROPS):
            continue
        for match in LITERAL_SIZE_RE.finditer(line):
            value = match.group(0)
            if value in SIZE_WHITELIST_VALUES:
                continue
            findings.append(Finding(path, line_no, "尺寸", value, line))

    return findings


def main() -> int:
    parser = argparse.ArgumentParser(description="检查 CSS 是否使用字面量（应使用设计令牌）")
    parser.add_argument("target", nargs="?", help="指定 CSS 文件或目录")
    args = parser.parse_args()

    if args.target:
        target = Path(args.target).resolve()
        if target.is_file():
            files = [target]
        elif target.is_dir():
            files = sorted(target.rglob("*.css"))
        else:
            print(f"[ERR]  路径不存在：{target}")
            return 1
    else:
        if not CSS_DIR.exists():
            print(f"[WARN] CSS 源目录不存在：{CSS_DIR.relative_to(ROOT).as_posix()}（P0 骨架阶段属正常）")
            return 0
        files = sorted(CSS_DIR.glob("*.css"))

    governed = [f for f in files if f.name in GOVERNED_FILES and f.name not in EXEMPT_FILES]
    if not governed:
        print("[OK]   没有需要检查的 CSS 文件（base/components/site）。")
        return 0

    findings: list[Finding] = []
    for path in governed:
        findings.extend(scan_file(path))

    if findings:
        for f in findings:
            print(f.report())
        print()
        print(f"[FAIL] 发现 {len(findings)} 处字面量，请改用 var(--...) 令牌")
        print("       确属必要的例外：在同行加注释 " + EXEMPT_MARKER)
        return 1

    names = ", ".join(f.name for f in governed)
    print(f"[OK]   CSS 令牌检查通过（{names}）")
    return 0


if __name__ == "__main__":
    sys.exit(main())
