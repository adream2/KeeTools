#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""共享逻辑同步脚本。

把 tools/_shared/ 下的逻辑 / UI / 图标片段，幂等注入到各工具 index.html 的
ET:INLINE 标记之间。

用法：
    python scripts/sync_shared.py                # 同步全部工具
    python scripts/sync_shared.py <tool-id>      # 只同步指定工具
    python scripts/sync_shared.py --check        # 只检查是否已同步（CI / pre-commit）

退出码：0 = 通过，1 = 有错误（--check 下表示存在未同步）

规则见 docs/工具开发规范.md §6。
"""

import argparse
import json
import re
import sys
from pathlib import Path

# ── 路径 ────────────────────────────────────────────────
ROOT = Path(__file__).resolve().parent.parent
TOOLS_DIR = ROOT / "tools"
SHARED_DIR = TOOLS_DIR / "_shared"
SHARED_MANIFEST = SHARED_DIR / "shared.manifest.json"

# 标记形如：
# <!-- ET:INLINE name="random-pick" src="logic/random-pick.js" -->
# ... 自动生成内容 ...
# <!-- /ET:INLINE -->
BLOCK_RE = re.compile(
    r"<!--\s*ET:INLINE\s+name=\"([^\"]+)\"\s+src=\"([^\"]+)\"(?:\s+type=\"([^\"]+)\")?\s*-->"
    r"(.*?)"
    r"<!--\s*/ET:INLINE\s*-->",
    re.DOTALL,
)


def guess_type(src: str, declared: str | None) -> str:
    """推断注入类型：显式声明 > 按扩展名推断。"""
    if declared:
        return declared.lower()
    suffix = Path(src).suffix.lower()
    if suffix == ".css":
        return "css"
    if suffix == ".svg":
        return "svg"
    return "js"


def load_shared_manifest() -> dict:
    """读取 shared.manifest.json；缺失时返回空字典（允许纯标记驱动）。"""
    if not SHARED_MANIFEST.exists():
        return {}
    try:
        with SHARED_MANIFEST.open("r", encoding="utf-8") as fh:
            data = json.load(fh)
    except json.JSONDecodeError as exc:
        print(f"[ERR]  shared.manifest.json 解析失败：{exc}")
        return {}
    if not isinstance(data, dict):
        print("[ERR]  shared.manifest.json 顶层必须是对象")
        return {}
    return data


def resolve_src(tool_id: str, name: str, src: str, manifest: dict) -> Path:
    """解析片段真实路径：manifest 声明优先，否则用标记内的 src。

    安全问题：src 必须落在 tools/_shared/ 内（防路径穿越）。
    """
    declared = manifest.get(tool_id, {}).get(name)
    rel = declared or src

    target = (SHARED_DIR / rel).resolve()
    try:
        target.relative_to(SHARED_DIR.resolve())
    except ValueError:
        raise ValueError(f"片段路径越出 _shared/：{rel}")
    return target


def normalize_newlines(text: str) -> str:
    """统一为 LF，并保证末尾恰好一个换行。"""
    text = text.replace("\r\n", "\n").replace("\r", "\n")
    text = text.rstrip("\n")
    return text + "\n"


def build_block(name: str, src: str, kind: str, body: str) -> str:
    """构造一个完整的 ET:INLINE 块（含标记头尾）。"""
    type_attr = f' type="{kind}"' if kind != "js" else ""
    head = f'<!-- ET:INLINE name="{name}" src="{src}"{type_attr} -->'
    tail = "<!-- /ET:INLINE -->"

    banner = "  /* 此块由 scripts/sync_shared.py 自动注入，请勿手工修改 */"
    if kind == "js":
        inner_prefix = "<script>\n" + banner + "\n"
        inner_suffix = "\n</script>"
    elif kind == "css":
        inner_prefix = "<style>\n" + banner + "\n"
        inner_suffix = "\n</style>"
    else:  # svg
        inner_prefix, inner_suffix = "", ""

    body = body.rstrip("\n")
    return f"{head}\n{inner_prefix}{body}{inner_suffix}\n{tail}"


def sync_file(html_path: Path, tool_id: str, manifest: dict, check_only: bool) -> tuple[int, int, list[str]]:
    """处理单个工具的 index.html。

    返回 (替换数, 未变更数, 消息列表)。
    """
    raw = html_path.read_text(encoding="utf-8")
    changed = 0
    unchanged = 0
    messages: list[str] = []

    def _replace(match: re.Match) -> str:
        nonlocal changed, unchanged
        name, src, declared_type = match.group(1), match.group(2), match.group(3)
        current = match.group(0)

        try:
            kind = guess_type(src, declared_type)
            piece = resolve_src(tool_id, name, src, manifest)
        except ValueError as exc:
            messages.append(f"[ERR]  {tool_id}  标记 {name}：{exc}")
            return current

        if not piece.exists():
            messages.append(f"[ERR]  {tool_id}  片段缺失：{piece.relative_to(ROOT).as_posix()}")
            return current

        body = piece.read_text(encoding="utf-8")
        if kind == "js" and "</script>" in body:
            messages.append(f"[WARN] {tool_id}  片段 {name} 含 </script>，会破坏 HTML 结构")
        new_block = build_block(name, src, kind, normalize_newlines(body))

        if new_block == current:
            unchanged += 1
            return current

        changed += 1
        if not check_only:
            messages.append(f"[SYNC] {tool_id}  {name}  ({piece.relative_to(ROOT).as_posix()})")
        return new_block

    result = BLOCK_RE.sub(_replace, raw)

    if changed and not check_only:
        html_path.write_text(result, encoding="utf-8", newline="\n")

    return changed, unchanged, messages


def iter_tools(only: str | None):
    """遍历待处理工具目录（跳过 _shared 与 _ 前缀目录）。"""
    if not TOOLS_DIR.exists():
        return
    for entry in sorted(TOOLS_DIR.iterdir()):
        if not entry.is_dir():
            continue
        if entry.name.startswith("_"):
            continue
        if only and entry.name != only:
            continue
        yield entry


def main() -> int:
    parser = argparse.ArgumentParser(description="同步 _shared 共享逻辑到各工具")
    parser.add_argument("tool_id", nargs="?", help="只同步指定工具 id")
    parser.add_argument("--check", action="store_true", help="只检查，不写入（未同步即失败）")
    args = parser.parse_args()

    if not SHARED_DIR.exists():
        print(f"[WARN] 共享目录不存在：{SHARED_DIR.relative_to(ROOT).as_posix()}")
        print("       跳过同步（P0 骨架阶段属正常）。")
        return 0

    manifest = load_shared_manifest()
    total_changed = 0
    total_unchanged = 0
    all_messages: list[str] = []
    tool_count = 0

    for tool_dir in iter_tools(args.tool_id):
        html_path = tool_dir / "index.html"
        if not html_path.exists():
            continue
        tool_count += 1
        has_marker = "ET:INLINE" in html_path.read_text(encoding="utf-8")
        if not has_marker:
            continue
        changed, unchanged, messages = sync_file(html_path, tool_dir.name, manifest, args.check)
        total_changed += changed
        total_unchanged += unchanged
        all_messages.extend(messages)

    for line in all_messages:
        print(line)

    if args.check:
        if total_changed:
            print(f"\n[FAIL] {total_changed} 个共享块未同步，请运行：python scripts/sync_shared.py")
            return 1
        print(f"[OK]   共享逻辑已同步（{tool_count} 个工具，{total_unchanged} 个块）")
        return 0

    if not tool_count:
        print("[OK]   没有需要处理的工具。")
        return 0

    print(f"\n[OK]   完成：{total_changed} 个块更新，{total_unchanged} 个块无变化（{tool_count} 个工具）")
    return 0


if __name__ == "__main__":
    sys.exit(main())
