#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""零外部依赖检查脚本。

扫描产出物（工具 HTML、网站 CSS/JS、模板）中的外部域名 URL。
任何 http(s):// 且不属于本站 / localhost 的地址都视为违规。

用法：
    python scripts/check_no_external.py             # 扫描全部产出物
    python scripts/check_no_external.py <路径>       # 扫描指定文件或目录

退出码：0 = 无外部依赖，1 = 发现外部依赖

规则见 AGENTS.md §3.1、docs/需求文档-v2.md。
"""

import argparse
import re
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent

# 需要扫描的目录（仅产出物；docs/ tasks/ 属文档，不扫描）
SCAN_DIRS = [
    "tools",
    "public",
    "src",
    "assets-src",
]

SCAN_SUFFIXES = {".html", ".htm", ".css", ".js", ".php", ".json", ".svg", ".txt", ".webmanifest"}

# 排除：开发期共享源不需要零依赖约束之外的检查？—— 不，_shared 也属产出物，一并检查
EXCLUDE_DIR_NAMES = {"var", "storage", "packages", "node_modules", ".git", "__pycache__"}

# 匹配绝对 URL
URL_RE = re.compile(r"""https?://([A-Za-z0-9._\-]+)""")

# 允许的域名/主机（本机与保留地址）
ALLOWED_HOSTS = {
    "localhost",
    "127.0.0.1",
    "0.0.0.0",
    "::1",
    "example.com",
    "example.org",
}

# 允许的 XML 命名空间（不是网络请求）
NAMESPACE_HOSTS = {"www.w3.org", "schema.org"}

# 行内豁免标记
EXEMPT_MARKER = "et-allow-external"

# 本站域名（部署后替换；相对路径优先，此处只是兜底白名单）
SITE_HOSTS = {
    "keetools.cn",
    "www.keetools.cn",
}


class Finding:
    def __init__(self, path: Path, line_no: int, host: str, line: str) -> None:
        self.path = path
        self.line_no = line_no
        self.host = host
        self.line = line.strip()

    def report(self) -> str:
        rel = self.path.relative_to(ROOT).as_posix()
        snippet = self.line if len(self.line) <= 100 else self.line[:97] + "..."
        return f"[ERR]  {rel}:{self.line_no}  →  {self.host}\n         {snippet}"


def is_exempt(line: str) -> bool:
    return EXEMPT_MARKER in line


def scan_file(path: Path) -> list[Finding]:
    findings: list[Finding] = []
    try:
        text = path.read_text(encoding="utf-8", errors="replace")
    except OSError:
        return findings

    for idx, line in enumerate(text.splitlines(), start=1):
        if is_exempt(line):
            continue
        for match in URL_RE.finditer(line):
            host = match.group(1)
            if host in ALLOWED_HOSTS or host in SITE_HOSTS:
                continue
            # XML 命名空间（xmlns="http://www.w3.org/..."）不是网络请求
            if host in NAMESPACE_HOSTS and "xmlns" in line:
                continue
            findings.append(Finding(path, idx, host, line))
    return findings


def iter_files(target: Path):
    if target.is_file():
        if target.suffix.lower() in SCAN_SUFFIXES:
            yield target
        return
    for path in sorted(target.rglob("*")):
        if not path.is_file():
            continue
        if any(part in EXCLUDE_DIR_NAMES for part in path.parts):
            continue
        if path.suffix.lower() in SCAN_SUFFIXES:
            yield path


def main() -> int:
    parser = argparse.ArgumentParser(description="检查产出物是否存在外部域名依赖")
    parser.add_argument("target", nargs="?", help="指定扫描的文件或目录（默认扫描全部产出物）")
    args = parser.parse_args()

    if args.target:
        targets = [Path(args.target).resolve()]
    else:
        targets = [ROOT / d for d in SCAN_DIRS]

    findings: list[Finding] = []
    scanned = 0

    for target in targets:
        if not target.exists():
            continue
        for path in iter_files(target):
            scanned += 1
            findings.extend(scan_file(path))

    if findings:
        for f in findings:
            print(f.report())
        print()
        print(f"[FAIL] 发现 {len(findings)} 处外部依赖（扫描 {scanned} 个文件）")
        print("       如确属必要（如 SVG 命名空间），在同行加注释 " + EXEMPT_MARKER)
        return 1

    print(f"[OK]   零外部依赖（扫描 {scanned} 个文件）")
    return 0


if __name__ == "__main__":
    sys.exit(main())
