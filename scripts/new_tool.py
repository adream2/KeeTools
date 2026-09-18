#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""新工具脚手架（P4 批量生产流水线）。

生成 tools/{id}/ 骨架：
    manifest.json  —— 契约（docs/manifest规范.md §3）
    index.html     —— 含 ET-META + ET:INLINE 标记 + et-chrome 可用外壳
    CHANGELOG.md   —— 变更记录
并自动完成：
    1. 在 tools/_shared/shared.manifest.json 登记共享片段
    2. 跑一次 sync_shared.py 把共享片段内联进 HTML
    3. 跑 check_manifest.py / check_no_external.py 自检
    4. 体积守卫：HTML 超过 TOOL_MAX_KB（默认 500KB）时告警

模板来源：tools/_template/*.tpl（唯一来源，改模板不必改本脚本）

用法：
    python scripts/new_tool.py                             # 交互式输入
    python scripts/new_tool.py --id my-tool --title "我的工具" \
        --type shell --subjects 通用 --grades 1-12 --yes

退出码：0 = 成功，1 = 失败

规范见 docs/工具开发规范.md §10、docs/manifest规范.md §7。
"""

import argparse
import json
import os
import re
import subprocess
import sys
from datetime import date
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
TOOLS_DIR = ROOT / "tools"
TEMPLATE_DIR = TOOLS_DIR / "_template"
SHARED_MANIFEST = TOOLS_DIR / "_shared" / "shared.manifest.json"

ID_RE = re.compile(r"^[a-z0-9-]+$")

VALID_TYPES = ("fixed", "shell", "experiment")
VALID_GRADES = ("1-2", "3-4", "5-6", "1-6", "7-9", "10-12", "1-12")
VALID_SUBJECTS = (
    "通用", "语文", "数学", "英语", "物理", "化学", "生物", "政治",
    "历史", "地理", "科学", "信息技术", "体育", "音乐", "美术",
)

# 每个学科一套默认 accent，避免批量产物颜色雷同（品质基线：各工具独立 accent）
SUBJECT_ACCENTS = {
    "通用": "#2563eb", "语文": "#dc2626", "数学": "#0891b2", "英语": "#7c3aed",
    "物理": "#4f46e5", "化学": "#059669", "生物": "#16a34a", "政治": "#b91c1c",
    "历史": "#b45309", "地理": "#0d9488", "科学": "#0284c7", "信息技术": "#4338ca",
    "体育": "#ea580c", "音乐": "#db2777", "美术": "#c026d3",
}

# 默认引用的共享片段（键 = ET:INLINE 的 name，值 = _shared 内相对路径）
DEFAULT_SHARED = {
    "et-util": "logic/et-util.js",
    "icons": "icons/icons.svg",
    "et-chrome-css": "ui/et-chrome.css",
    "et-chrome": "ui/et-chrome.js",
    "et-stats": "ui/et-stats.js",
}

TEMPLATES = {
    "manifest.json": "manifest.json.tpl",
    "index.html": "index.html.tpl",
    "CHANGELOG.md": "CHANGELOG.md.tpl",
}


def prompt(question: str, default: str = "") -> str:
    suffix = f"（默认 {default}）" if default else ""
    answer = input(f"{question}{suffix}：".strip() + " ").strip()
    return answer or default


def pick(question: str, options: tuple[str, ...], default: str) -> str:
    while True:
        print(question)
        print("  " + " / ".join(options))
        answer = prompt(">", default)
        if answer in options:
            return answer
        print(f"  [ERR] 取值必须是：{', '.join(options)}")


def class_prefix(tool_id: str) -> str:
    """工具 id → 自身 CSS 类名前缀（去连字符，取前 12 字符）。"""
    return re.sub(r"[^a-z0-9]", "", tool_id)[:12] or "tool"


def build_tokens(params: dict, version: str = "1.0.0") -> dict:
    subjects = params["subjects"]
    primary = subjects[0] if subjects else "通用"
    accent = params["accent"] or SUBJECT_ACCENTS.get(primary, "#2563eb")

    return {
        "@@TOOL_ID@@": params["tool_id"],
        "@@TITLE@@": params["title"],
        "@@VERSION@@": version,
        "@@TYPE@@": params["ttype"],
        "@@DESCRIPTION@@": params["description"],
        "@@AUTHOR@@": params["author"],
        "@@SUB@@": " · ".join(subjects) if subjects else "课堂工具",
        "@@ACCENT@@": accent,
        "@@PREFIX@@": class_prefix(params["tool_id"]),
        "@@GRADES_JSON@@": json.dumps(params["grades"], ensure_ascii=False),
        "@@SUBJECTS_JSON@@": json.dumps(subjects, ensure_ascii=False),
        "@@TAGS_JSON@@": json.dumps(subjects, ensure_ascii=False),
        "@@FAMILY_JSON@@": json.dumps(params["family"], ensure_ascii=False),
        "@@TODAY@@": date.today().isoformat(),
    }


def render(name: str, tokens: dict) -> str:
    path = TEMPLATE_DIR / TEMPLATES[name]
    text = path.read_text(encoding="utf-8")
    for token, value in tokens.items():
        text = text.replace(token, value)
    leftover = re.findall(r"@@[A-Z_]+@@", text)
    if leftover:
        raise ValueError(f"模板 {path.name} 存在未替换占位符：{', '.join(sorted(set(leftover)))}")
    return text


def build_files(params: dict) -> dict:
    if not TEMPLATE_DIR.exists():
        raise FileNotFoundError(f"模板目录不存在：{TEMPLATE_DIR.relative_to(ROOT).as_posix()}")
    tokens = build_tokens(params)
    files = {name: render(name, tokens) for name in TEMPLATES}

    # manifest 必须是合法 JSON（模板改坏时立刻暴露，而不是等扫描失败）
    json.loads(files["manifest.json"])
    return files


def gather_args(args) -> dict:
    """命令行参数优先，缺失则交互式询问；--yes 模式下缺失项直接取默认值。"""
    interactive = not args.yes

    tool_id = args.id or (prompt("工具 id（小写字母/数字/连字符）") if interactive else "")
    while not ID_RE.match(tool_id):
        print("  [ERR] id 只能包含小写字母、数字与连字符")
        tool_id = prompt("工具 id")

    title = args.title or (prompt("工具标题", tool_id) if interactive else tool_id)
    ttype = args.type or (pick("工具类型", VALID_TYPES, "shell") if interactive else "shell")

    if args.subjects:
        subjects = [s.strip() for s in args.subjects.split(",") if s.strip()]
    elif interactive:
        raw = prompt("学科（多个用逗号分隔）", "通用")
        subjects = [s.strip() for s in raw.split(",") if s.strip()]
    else:
        subjects = ["通用"]

    for s in subjects:
        if s not in VALID_SUBJECTS:
            print(f"  [WARN] 学科 '{s}' 不在推荐取值内，仍会写入")

    if args.grades:
        grades = [g.strip() for g in args.grades.split(",") if g.strip()]
    elif interactive:
        raw = prompt(f"学段（多个用逗号分隔，可选 {', '.join(VALID_GRADES)}）", "1-12")
        grades = [g.strip() for g in raw.split(",") if g.strip()]
    else:
        grades = ["1-12"]

    description = args.description or (
        prompt("一句话简介", f"{title}，适合课堂大屏使用。") if interactive
        else f"{title}，适合课堂大屏使用。"
    )
    author = args.author or (prompt("作者", "KeeTools Team") if interactive else "KeeTools Team")
    family = args.family or None
    accent = args.accent or None

    return {
        "tool_id": tool_id,
        "title": title,
        "ttype": ttype,
        "subjects": subjects,
        "grades": grades,
        "description": description,
        "author": author,
        "family": family,
        "accent": accent,
    }


def register_shared(tool_id: str) -> None:
    """把默认共享片段登记进 tools/_shared/shared.manifest.json。"""
    if not SHARED_MANIFEST.exists():
        print("[WARN] 未找到 shared.manifest.json，跳过共享片段登记")
        return

    try:
        data = json.loads(SHARED_MANIFEST.read_text(encoding="utf-8"))
    except json.JSONDecodeError as exc:
        print(f"[WARN] shared.manifest.json 解析失败（{exc}），跳过登记")
        return

    if not isinstance(data, dict):
        print("[WARN] shared.manifest.json 顶层非对象，跳过登记")
        return

    data[tool_id] = dict(DEFAULT_SHARED)
    SHARED_MANIFEST.write_text(
        json.dumps(data, ensure_ascii=False, indent=2) + "\n",
        encoding="utf-8",
        newline="\n",
    )
    print(f"[OK]   已登记 {len(DEFAULT_SHARED)} 个共享片段到 shared.manifest.json")


def max_tool_kb() -> int:
    try:
        return max(1, int(os.environ.get("TOOL_MAX_KB", "500")))
    except ValueError:
        return 500


def check_size(tool_dir: Path) -> None:
    entry = tool_dir / "index.html"
    if not entry.exists():
        return

    limit = max_tool_kb()
    size_kb = entry.stat().st_size / 1024
    if size_kb > limit:
        print(f"[WARN] index.html 体积 {size_kb:.0f}KB 超过 TOOL_MAX_KB={limit}KB")
        print("       考虑：抽共享逻辑到 tools/_shared/，或登记 docs/工具例外清单.md")
    else:
        print(f"[OK]   体积 {size_kb:.0f}KB（上限 {limit}KB）")


def run_step(label: str, cmd: list[str]) -> bool:
    result = subprocess.run(cmd, cwd=str(ROOT), check=False)
    if result.returncode != 0:
        print(f"[WARN] {label} 未通过，请修复后重跑")
        return False
    return True


def main() -> int:
    parser = argparse.ArgumentParser(description="生成新工具骨架（含共享片段内联与自检）")
    parser.add_argument("--id", help="工具 id（等于目录名）")
    parser.add_argument("--title", help="工具标题")
    parser.add_argument("--type", choices=VALID_TYPES, help="工具类型")
    parser.add_argument("--subjects", help="学科，逗号分隔")
    parser.add_argument("--grades", help="学段，逗号分隔")
    parser.add_argument("--description", help="一句话简介")
    parser.add_argument("--author", help="作者")
    parser.add_argument("--family", help="family（同算法的不同玩法，kebab-case）")
    parser.add_argument("--accent", help="主题色（默认按首个学科自动选取）")
    parser.add_argument("--yes", action="store_true", help="跳过交互（缺参数时报错）")
    args = parser.parse_args()

    if args.yes and not (args.id and args.title):
        print("[ERR]  --yes 模式必须提供至少 --id 与 --title")
        return 1

    if not args.yes and not (args.id and args.title):
        print("== 新建工具（直接回车使用默认值）==\n")

    params = gather_args(args)
    tool_id = params["tool_id"]
    tool_dir = TOOLS_DIR / tool_id

    if tool_dir.exists():
        print(f"[ERR]  目录已存在：{tool_dir.relative_to(ROOT).as_posix()}")
        return 1

    try:
        files = build_files(params)
    except (FileNotFoundError, ValueError, json.JSONDecodeError) as exc:
        print(f"[ERR]  生成失败：{exc}")
        return 1

    tool_dir.mkdir(parents=True)
    for name, content in files.items():
        (tool_dir / name).write_text(content, encoding="utf-8", newline="\n")

    print(f"\n[OK]   已创建 {tool_dir.relative_to(ROOT).as_posix()}/")
    for name in files:
        print(f"       - {name}")

    print("\n== 共享片段登记 ==")
    register_shared(tool_id)

    print("\n== 内联共享片段 ==")
    run_step("sync_shared", [sys.executable, str(ROOT / "scripts" / "sync_shared.py"), tool_id])

    print("\n== 自检 ==")
    results = [
        run_step("manifest", [sys.executable, str(ROOT / "scripts" / "check_manifest.py"), tool_id]),
        run_step("external", [sys.executable, str(ROOT / "scripts" / "check_no_external.py"), str(tool_dir)]),
    ]
    check_size(tool_dir)

    print("\n下一步：")
    print(f"  1. 实现 tools/{tool_id}/index.html 的功能（ET:INLINE 标记内禁止手改）")
    print("  2. 后台点『扫描同步』或运行扫描脚本入库")
    print(f'  3. git add tools/{tool_id} && git commit -m "tool({tool_id}): v1.0.0 初始版本"')

    return 0 if all(results) else 1


if __name__ == "__main__":
    sys.exit(main())
