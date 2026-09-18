#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""新工具脚手架。

生成 tools/{id}/ 骨架：manifest.json + index.html（含 ET-META 与 ET:INLINE 标记）
+ CHANGELOG.md，并自动跑一次校验。

用法：
    python scripts/new_tool.py                             # 交互式输入
    python scripts/new_tool.py --id my-tool --title "我的工具" \
        --type shell --subjects 通用 --grades 1-12

退出码：0 = 成功，1 = 失败

规范见 docs/工具开发规范.md §10、docs/manifest规范.md §7。
"""

import argparse
import json
import re
import subprocess
import sys
from datetime import date
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
TOOLS_DIR = ROOT / "tools"

ID_RE = re.compile(r"^[a-z0-9-]+$")

VALID_TYPES = ("fixed", "shell", "experiment")
VALID_GRADES = ("1-2", "3-4", "5-6", "1-6", "7-9", "10-12", "1-12")
VALID_SUBJECTS = (
    "通用", "语文", "数学", "英语", "物理", "化学", "生物", "政治",
    "历史", "地理", "科学", "信息技术", "体育", "音乐", "美术",
)

MANIFEST_FIELD_ORDER = [
    "id", "title", "version", "type", "description", "author", "entry",
    "single_file", "offline", "grade_range", "subjects", "tags", "family",
    "license", "dependencies", "screen", "stats_enabled", "created_at", "updated_at",
]

HTML_TEMPLATE = """<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{title} — KeeTools 课工具</title>
<!-- ET-META
{{"id":"{tool_id}","version":"{version}"}}
-->
<style>
/* 工具样式：自包含，不引用任何外部资源 */
:root {{
  --bg: #f5f6f8;
  --fg: #1f2328;
  --accent: #2563eb;
}}
* {{ box-sizing: border-box; }}
html, body {{ height: 100%; }}
body {{
  margin: 0;
  background: var(--bg);
  color: var(--fg);
  font-family: "Microsoft YaHei", "PingFang SC", system-ui, sans-serif;
  font-size: 16px;
}}
#app {{
  min-height: 100%;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 16px;
  padding: 24px;
}}
</style>
</head>
<body>

<!-- 页面结构 -->
<main id="app">
  <h1 style="font-size: 48px; margin: 0;">{title}</h1>
  <p style="font-size: 20px; color: #57606a;">工具骨架已就绪，开始实现吧。</p>
</main>

<script>
(function () {{
  'use strict';

  var LS_PREFIX = 'et_{tool_id}_';

  function el(id) {{ return document.getElementById(id); }}

  function init() {{
    // TODO: 实现工具逻辑
    console.log('LS prefix:', LS_PREFIX);
  }}

  if (document.readyState === 'loading') {{
    document.addEventListener('DOMContentLoaded', init);
  }} else {{
    init();
  }}
}})();
</script>

</body>
</html>
"""

CHANGELOG_TEMPLATE = """# {title} 更新记录

遵循 [Keep a Changelog](https://keepachangelog.com/zh-CN/1.1.0/) 与[语义化版本](https://semver.org/lang/zh-CN/)。

## [Unreleased]

## [{version}] - {today}

### 新增

- 初始版本
"""


def prompt(question: str, default: str = "") -> str:
    suffix = f"（默认 {default}）" if default else ""
    answer = input(f"{question}{suffix}：".strip() + " ").strip()
    return answer or default


def pick(question: str, options: tuple[str, ...], default: str) -> str:
    while True:
        print(f"{question}")
        print("  " + " / ".join(options))
        answer = prompt(">", default)
        if answer in options:
            return answer
        print(f"  [ERR] 取值必须是：{', '.join(options)}")


def ordered_manifest(data: dict) -> dict:
    return {k: data[k] for k in MANIFEST_FIELD_ORDER if k in data}


def build_files(tool_id: str, title: str, ttype: str, subjects: list[str],
                grades: list[str], description: str, author: str) -> dict:
    today = date.today().isoformat()
    version = "1.0.0"

    manifest = ordered_manifest({
        "id": tool_id,
        "title": title,
        "version": version,
        "type": ttype,
        "description": description,
        "author": author,
        "entry": "index.html",
        "single_file": True,
        "offline": True,
        "grade_range": grades,
        "subjects": subjects,
        "tags": [s for s in subjects],
        "license": "free",
        "dependencies": [],
        "screen": "large",
        "stats_enabled": True,
        "created_at": today,
        "updated_at": today,
    })

    return {
        "manifest.json": json.dumps(manifest, ensure_ascii=False, indent=2) + "\n",
        "index.html": HTML_TEMPLATE.format(tool_id=tool_id, title=title, version=version),
        "CHANGELOG.md": CHANGELOG_TEMPLATE.format(title=title, version=version, today=today),
    }


def gather_args(args) -> dict:
    """命令行参数优先，缺失则交互式询问。"""
    tool_id = args.id or prompt("工具 id（小写字母/数字/连字符）")
    while not ID_RE.match(tool_id):
        print("  [ERR] id 只能包含小写字母、数字与连字符")
        tool_id = prompt("工具 id")

    title = args.title or prompt("工具标题", tool_id)
    ttype = args.type or pick("工具类型", VALID_TYPES, "shell")

    if args.subjects:
        subjects = [s.strip() for s in args.subjects.split(",") if s.strip()]
    else:
        raw = prompt("学科（多个用逗号分隔）", "通用")
        subjects = [s.strip() for s in raw.split(",") if s.strip()]
    for s in subjects:
        if s not in VALID_SUBJECTS:
            print(f"  [WARN] 学科 '{s}' 不在推荐取值内，仍会写入")

    if args.grades:
        grades = [g.strip() for g in args.grades.split(",") if g.strip()]
    else:
        raw = prompt(f"学段（多个用逗号分隔，可选 {', '.join(VALID_GRADES)}）", "1-12")
        grades = [g.strip() for g in raw.split(",") if g.strip()]

    description = args.description or prompt(
        "一句话简介", f"{title}，适合课堂大屏使用。")
    author = args.author or prompt("作者", "KeeTools Team")

    return {
        "tool_id": tool_id,
        "title": title,
        "ttype": ttype,
        "subjects": subjects,
        "grades": grades,
        "description": description,
        "author": author,
    }


def main() -> int:
    parser = argparse.ArgumentParser(description="生成新工具骨架")
    parser.add_argument("--id", help="工具 id（等于目录名）")
    parser.add_argument("--title", help="工具标题")
    parser.add_argument("--type", choices=VALID_TYPES, help="工具类型")
    parser.add_argument("--subjects", help="学科，逗号分隔")
    parser.add_argument("--grades", help="学段，逗号分隔")
    parser.add_argument("--description", help="一句话简介")
    parser.add_argument("--author", help="作者")
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

    files = build_files(**params)
    tool_dir.mkdir(parents=True)
    for name, content in files.items():
        (tool_dir / name).write_text(content, encoding="utf-8", newline="\n")

    print(f"\n[OK]   已创建 {tool_dir.relative_to(ROOT).as_posix()}/")
    for name in files:
        print(f"       - {name}")

    # ── 自动校验 ─────────────────────────────
    print("\n== 自动校验 ==\n")
    checks = [
        ("manifest", [sys.executable, str(ROOT / "scripts" / "check_manifest.py"), tool_id]),
        ("external", [sys.executable, str(ROOT / "scripts" / "check_no_external.py"),
                      str(tool_dir)]),
    ]
    failed = False
    for label, cmd in checks:
        result = subprocess.run(cmd, cwd=str(ROOT), check=False)
        if result.returncode != 0:
            failed = True
            print(f"[WARN] {label} 校验未通过，请修复后重跑")

    print("\n下一步：")
    print(f"  1. 实现 tools/{tool_id}/index.html 的功能")
    print("  2. 后台点『扫描同步』或运行扫描脚本入库")
    print(f'  3. git add tools/{tool_id} && git commit -m "tool({tool_id}): v1.0.0 初始版本"')

    return 1 if failed else 0


if __name__ == "__main__":
    sys.exit(main())
