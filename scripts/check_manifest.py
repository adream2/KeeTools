#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""manifest.json 校验脚本。

校验 tools/*/manifest.json 的完整性，并比对 index.html 内的 ET-META 块。

用法：
    python scripts/check_manifest.py            # 校验全部
    python scripts/check_manifest.py <tool-id>  # 只校验指定工具

退出码：0 = 全部通过（允许 WARN），1 = 存在 ERR

规则见 docs/manifest规范.md §5。
"""

import argparse
import json
import re
import sys
from datetime import date
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
TOOLS_DIR = ROOT / "tools"

ID_RE = re.compile(r"^[a-z0-9-]+$")
VERSION_RE = re.compile(r"^\d+\.\d+\.\d+$")
DATE_RE = re.compile(r"^\d{4}-\d{2}-\d{2}$")

# 形如：<!-- ET-META {"id":"x","version":"1.0.0"} -->
ET_META_RE = re.compile(r"<!--\s*ET-META\s*(\{.*?\})\s*-->", re.DOTALL)

REQUIRED_STR = ["id", "title", "version", "type", "description", "author", "entry"]
REQUIRED_BOOL = ["single_file", "offline"]
# 必须非空
REQUIRED_ARR = ["grade_range", "subjects", "tags"]
# 必须存在且为数组，但允许为空（单文件工具即空数组）
ARRAY_FIELDS = ["dependencies"]

VALID_TYPES = {"fixed", "shell", "experiment"}

GRADE_VALUES = {"1-2", "3-4", "5-6", "1-6", "7-9", "10-12", "1-12"}

SUBJECT_VALUES = {
    "通用", "语文", "数学", "英语", "物理", "化学", "生物",
    "政治", "历史", "地理", "科学", "信息技术", "体育", "音乐", "美术",
}

VALID_SCREEN = {"large", "any"}

MAX_TITLE_LEN = 40
MAX_DESC_LEN = 60


class Result:
    """单个工具的校验结果。"""

    def __init__(self, tool_id: str, version: str = "-") -> None:
        self.tool_id = tool_id
        self.version = version
        self.errors: list[str] = []
        self.warnings: list[str] = []

    def err(self, msg: str) -> None:
        self.errors.append(msg)

    def warn(self, msg: str) -> None:
        self.warnings.append(msg)

    def report(self) -> str:
        if self.errors:
            tag = "[ERR] "
            detail = "；".join(self.errors)
        elif self.warnings:
            tag = "[WARN]"
            detail = "；".join(self.warnings)
        else:
            tag = "[OK]  "
            detail = ""
        head = f"{tag} {self.tool_id:<28} v{self.version}"
        return f"{head}  {detail}".rstrip()


def parse_date(value: str) -> date | None:
    if not isinstance(value, str) or not DATE_RE.match(value):
        return None
    try:
        return date.fromisoformat(value)
    except ValueError:
        return None


def validate_str_field(res: Result, data: dict, key: str) -> None:
    value = data.get(key)
    if not isinstance(value, str) or not value.strip():
        res.err(f"{key} 缺失或非字符串")


def validate_arr_field(res: Result, data: dict, key: str, allow_empty: bool = False) -> None:
    value = data.get(key)
    if not isinstance(value, list):
        res.err(f"{key} 缺失或非数组")
        return
    if not value and not allow_empty:
        res.err(f"{key} 不能用空数组")
        return
    if not all(isinstance(item, str) and item.strip() for item in value):
        res.err(f"{key} 含非字符串或空元素")


def check_one(tool_dir: Path) -> Result:
    tool_id = tool_dir.name
    res = Result(tool_id)

    manifest_path = tool_dir / "manifest.json"
    if not manifest_path.exists():
        res.err("缺少 manifest.json")
        return res

    try:
        with manifest_path.open("r", encoding="utf-8") as fh:
            data = json.load(fh)
    except json.JSONDecodeError as exc:
        res.err(f"JSON 解析失败：{exc.msg} (行 {exc.lineno})")
        return res

    if not isinstance(data, dict):
        res.err("manifest 顶层必须是对象")
        return res

    if isinstance(data.get("version"), str):
        res.version = data["version"]

    # ── 必填字段 ─────────────────────────────
    for key in REQUIRED_STR:
        validate_str_field(res, data, key)
    for key in REQUIRED_BOOL:
        if not isinstance(data.get(key), bool):
            res.err(f"{key} 缺失或非布尔值")
    for key in REQUIRED_ARR:
        validate_arr_field(res, data, key)
    for key in ARRAY_FIELDS:
        validate_arr_field(res, data, key, allow_empty=True)

    # ── id ───────────────────────────────────
    mid = data.get("id")
    if isinstance(mid, str):
        if not ID_RE.match(mid):
            res.err(f"id 格式非法：{mid}")
        if mid != tool_id:
            res.err(f"id 与目录名不一致 (id: {mid}, dir: {tool_id})")

    # ── version ──────────────────────────────
    ver = data.get("version")
    if isinstance(ver, str) and not VERSION_RE.match(ver):
        res.err(f"version 非语义化版本：{ver}")

    # ── title / description 长度 ─────────────
    title = data.get("title")
    if isinstance(title, str) and len(title) > MAX_TITLE_LEN:
        res.warn(f"title 超过 {MAX_TITLE_LEN} 字符（{len(title)}）")
    desc = data.get("description")
    if isinstance(desc, str) and len(desc) > MAX_DESC_LEN:
        res.warn(f"description 超过 {MAX_DESC_LEN} 字符（{len(desc)}）")

    # ── type ─────────────────────────────────
    ttype = data.get("type")
    if isinstance(ttype, str) and ttype not in VALID_TYPES:
        res.err(f"type 取值非法：{ttype}（应为 {'/'.join(sorted(VALID_TYPES))}）")

    # ── entry ────────────────────────────────
    entry = data.get("entry")
    if isinstance(entry, str):
        if entry != "index.html":
            res.err(f"entry 必须为 index.html（当前 {entry}）")
        if not (tool_dir / entry).exists():
            res.err(f"entry 文件不存在：{entry}")

    # ── single_file + dependencies ───────────
    if data.get("single_file") is True:
        deps = data.get("dependencies")
        if isinstance(deps, list) and deps:
            res.err("single_file=true 但 dependencies 非空（应改 false 并登记例外清单）")

    # ── grade_range / subjects / screen ──────
    grades = data.get("grade_range")
    if isinstance(grades, list):
        for g in grades:
            if isinstance(g, str) and g not in GRADE_VALUES:
                res.err(f"grade_range 取值非法：{g}")

    subjects = data.get("subjects")
    if isinstance(subjects, list):
        for s in subjects:
            if isinstance(s, str) and s not in SUBJECT_VALUES:
                res.err(f"subjects 取值非法：{s}")

    screen = data.get("screen")
    if screen is not None and screen not in VALID_SCREEN:
        res.err(f"screen 取值非法：{screen}")

    family = data.get("family")
    if family is not None and not (isinstance(family, str) and ID_RE.match(family)):
        res.err(f"family 格式非法：{family}")

    # ── 日期 ─────────────────────────────────
    created = parse_date(data.get("created_at", ""))
    updated = parse_date(data.get("updated_at", ""))
    if created is None:
        res.err("created_at 缺失或格式非 YYYY-MM-DD")
    if updated is None:
        res.err("updated_at 缺失或格式非 YYYY-MM-DD")
    if created and updated and updated < created:
        res.err(f"updated_at 早于 created_at ({updated} < {created})")

    # ── CHANGELOG ────────────────────────────
    if not (tool_dir / "CHANGELOG.md").exists():
        res.err("缺少 CHANGELOG.md")

    # ── ET-META 一致性 ───────────────────────
    html_path = tool_dir / (entry if isinstance(entry, str) else "index.html")
    if html_path.exists():
        html = html_path.read_text(encoding="utf-8", errors="replace")
        meta_match = ET_META_RE.search(html)
        if not meta_match:
            res.err("index.html 缺少 ET-META 块")
        else:
            raw = meta_match.group(1)
            try:
                meta = json.loads(raw)
            except json.JSONDecodeError as exc:
                res.err(f"ET-META JSON 解析失败：{exc.msg}")
            else:
                if meta.get("id") != mid:
                    res.err(f"ET-META id 不一致 (manifest: {mid}, html: {meta.get('id')})")
                if meta.get("version") != ver:
                    res.err(f"ET-META version 不一致 (manifest: {ver}, html: {meta.get('version')})")

    return res


def iter_tool_dirs(only: str | None) -> list[Path]:
    if not TOOLS_DIR.exists():
        return []
    dirs = []
    for entry in sorted(TOOLS_DIR.iterdir()):
        if not entry.is_dir() or entry.name.startswith("_"):
            continue
        if only and entry.name != only:
            continue
        dirs.append(entry)
    return dirs


def main() -> int:
    parser = argparse.ArgumentParser(description="校验 manifest.json 与 ET-META 一致性")
    parser.add_argument("tool_id", nargs="?", help="只校验指定工具 id")
    args = parser.parse_args()

    if not TOOLS_DIR.exists():
        print("[WARN] tools/ 目录不存在，跳过校验。")
        return 0

    tool_dirs = iter_tool_dirs(args.tool_id)
    if not tool_dirs:
        print("[OK]   没有可校验的工具（tools/ 下无工具目录）。")
        return 0

    results = [check_one(d) for d in tool_dirs]
    for res in results:
        print(res.report())

    err_count = sum(len(r.errors) for r in results)
    warn_count = sum(len(r.warnings) for r in results)
    err_tools = sum(1 for r in results if r.errors)

    print()
    if err_count:
        print(f"{err_tools} 个工具存在错误，共 {err_count} 项错误，{warn_count} 项警告")
        return 1
    print(f"[OK]   {len(results)} 个工具全部通过，{warn_count} 项警告")
    return 0


if __name__ == "__main__":
    sys.exit(main())
