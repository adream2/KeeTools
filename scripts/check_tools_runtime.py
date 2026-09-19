#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""工具运行时抽检（JS 语法 + 无头浏览器渲染）。

**不属于 pre-commit 六项门禁**，是每批工具交付时的补充手段：
六项门禁只做静态校验，抓不到以下两类问题（见 `docs/工具开发规范.md` §6.4.1）：

  1. 工具 HTML 里误写了「完整的 `ET:INLINE` 标记文本」→ `sync_shared.py` 会把它当真标记注入
  2. 工具 HTML 里误写了「script 结束标签字面量」→ HTML 解析器提前闭合外层 `<script>`

两者都会表现为「门禁全绿但页面已损坏」，只有真正解析/执行 JS 才能发现。

做法：
  A. JS 语法：抽出每个 `<script>` 块交给 `node --check`
  B. 渲染回归：用无头 Chrome/Edge 打开 `file://` 页面，抓 DOM + 截图 + stderr 报错，
     校验 DOM 中出现了 `et-topbar` / `et-footer`（`ET.Chrome.mount()` 执行过的证据）
     以及各工具登记的关键标记

用法：
    python scripts/check_tools_runtime.py                # 全部工具
    python scripts/check_tools_runtime.py times-table    # 指定工具
    python scripts/check_tools_runtime.py --no-shot      # 跳过截图（更快）

产物：`var/tmp/render/{tool}.html`、`var/tmp/render/{tool}.png`（截图，供人工核对）

环境依赖与降级（首次 clone / 无 Node 无浏览器时**优雅跳过**，退出码 0）：
    - 无 `node`   → 跳过 A
    - 无 Chrome / Edge → 跳过 B

退出码：0 = 通过（含跳过），1 = 存在语法错误或渲染异常。
"""

import argparse
import json
import re
import shutil
import subprocess
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
TOOLS_DIR = ROOT / "tools"
OUT_DIR = ROOT / "var" / "tmp" / "render"
PROBE_DIR = ROOT / "var" / "tmp" / "render" / "probe"
PROFILE_DIR = ROOT / "var" / "tmp" / "chrome-profile"

SCRIPT_RE = re.compile(r"<script[^>]*>(.*?)</script>", re.DOTALL | re.IGNORECASE)

# 通用标记：et-chrome 挂载成功的证据（缺任一即说明脚本没跑到底）
COMMON_MARKERS = ['class="et-topbar"', 'class="et-footer"']

# 可选：各工具 JS 渲染出的关键标记（新增工具时可补充，缺省只做通用校验）
TOOL_MARKERS = {
    "times-table": ['class="tt-cell'],
    "fraction-visual": ['class="fv-slice'],
    "poem-cards": ['pc-card__title'],
    "function-grapher": ['fg-chip'],
    "periodic-table": ['class="pt-el'],
    "circuit-builder": ['cb-tool'],
    "lens-imaging": ['lc-check'],
    "pick-lottery": ['pl-item__idx'],
    "stroke-order": ['so-step'],
    "idiom-dict": ['idiomdict-card'],
    "geometry-builder": ['geometrybuil-tpl'],
    "clock-learning": ['clocklearnin-num'],
    "ipa-chart": ['ipachart-group'],
    "word-flashcard": ['wordflashcard-chip'],
    "probability-lab": ['probabilityl-legend__dash'],
    "pythagorean": ['pythagorean-tile'],
    "chem-balancer": ['chembalancer-coef'],
    "microscope-sim": ['id="fovSpecimen"'],
    "lever-balance": ['leverbalance-w'],
    "light-reflection": ['id="dragHandle"'],
    "buoyancy-lab": ['id="objRect"'],
    "pulley-lab": ['id="rope"'],
}

CHROME_CANDIDATES = [
    r"C:\Program Files\Google\Chrome\Application\chrome.exe",
    r"C:\Program Files (x86)\Google\Chrome\Application\chrome.exe",
    r"C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe",
    r"C:\Program Files\Microsoft\Edge\Application\msedge.exe",
]


def iter_tools(only: str | None) -> list[Path]:
    if not TOOLS_DIR.exists():
        return []
    return [
        d for d in sorted(TOOLS_DIR.iterdir())
        if d.is_dir() and not d.name.startswith("_") and (not only or d.name == only)
    ]


def tool_title(tool_dir: Path) -> str:
    manifest = tool_dir / "manifest.json"
    if not manifest.exists():
        return ""
    try:
        data = json.loads(manifest.read_text(encoding="utf-8"))
    except json.JSONDecodeError:
        return ""
    return str(data.get("title", ""))


def find_node() -> str | None:
    return shutil.which("node")


def find_chrome() -> str | None:
    for path in CHROME_CANDIDATES:
        if Path(path).exists():
            return path
    return shutil.which("chrome") or shutil.which("msedge")


def check_js(tool_dir: Path, node: str) -> list[str]:
    """抽 <script> 块跑 node --check，返回问题列表。"""
    html = (tool_dir / "index.html").read_text(encoding="utf-8")
    blocks = SCRIPT_RE.findall(html)
    problems: list[str] = []
    tmp_dir = OUT_DIR / "_js"
    tmp_dir.mkdir(parents=True, exist_ok=True)

    for i, body in enumerate(blocks):
        tmp = tmp_dir / f"{tool_dir.name}-{i}.js"
        tmp.write_text(body, encoding="utf-8", newline="\n")
        result = subprocess.run([node, "--check", str(tmp)], capture_output=True, text=True)
        if result.returncode != 0:
            first = [ln for ln in result.stderr.strip().splitlines() if ln.strip()][:3]
            problems.append(f"script 块 #{i} 语法错误：{' | '.join(first)}")
        tmp.unlink()
    return problems


ERROR_HOOK = """<script>
window.__etErr = '';
window.addEventListener('error', function (ev) {
    window.__etErr = 'ERR:' + ev.message + '@' + (ev.lineno || '?');
    document.title = window.__etErr;
});
</script>"""


def check_render(tool_dir: Path, chrome: str, shot: bool) -> tuple[list[str], str, int]:
    """无头渲染，返回 (问题列表, DOM, 截图字节数)。

    会把 index.html 复制到 var/tmp 并注入 window.onerror → document.title，
    这样「脚本抛错但 DOM 仍有内容」的情况也能被测出来（Chrome 无头默认不把
    控制台报错写进 stderr）。
    """
    src = (tool_dir / "index.html").read_text(encoding="utf-8")
    probe = src.replace("<head>", "<head>\n" + ERROR_HOOK, 1)
    if probe == src:  # 没有 <head> 的极端情况：退化为直接渲染原文件
        probe = src
    probe_path = PROBE_DIR / f"{tool_dir.name}.html"
    probe_path.write_text(probe, encoding="utf-8", newline="\n")
    url = probe_path.as_uri()

    base = [
        chrome, "--headless=new", "--disable-gpu", "--no-first-run",
        f"--user-data-dir={PROFILE_DIR}", "--virtual-time-budget=2200",
        "--window-size=1600,900", "--hide-scrollbars",
    ]
    dom_proc = subprocess.run(base + ["--dump-dom", url], capture_output=True,
                              text=True, encoding="utf-8", errors="replace")
    html = dom_proc.stdout or ""
    stderr = dom_proc.stderr or ""
    shot_bytes = 0

    if shot:
        shot_path = OUT_DIR / f"{tool_dir.name}.png"
        ring = subprocess.run(base + [f"--screenshot={shot_path}", url],
                              capture_output=True, text=True, encoding="utf-8", errors="replace")
        stderr += ring.stderr or ""
        if shot_path.exists():
            shot_bytes = shot_path.stat().st_size

    problems: list[str] = []
    for token in sorted(set(re.findall(r"Uncaught \w+", stderr))):
        problems.append(f"控制台报错：{token}")
    if "SyntaxError" in stderr:
        problems.append("控制台出现 SyntaxError")

    if not html.strip():
        problems.append("DOM 为空（页面未渲染）")
        return problems, html, shot_bytes

    err = re.search(r"<title>ERR:([^<]*)</title>", html)
    if err:
        problems.append(f"运行时未捕获异常：{err.group(1)}")

    expected = list(COMMON_MARKERS) + TOOL_MARKERS.get(tool_dir.name, [])
    title = tool_title(tool_dir)
    if title and not err and f"<title>{title}" not in html:
        problems.append(f"页面标题与 manifest 不一致（应为「{title}」开头）")
    for marker in expected:
        if marker not in html:
            problems.append(f"DOM 缺少标记：{marker}")

    return problems, html, shot_bytes


def main() -> int:
    parser = argparse.ArgumentParser(description="工具 JS 语法 + 无头渲染抽检（非门禁，交付前补充）")
    parser.add_argument("tool_id", nargs="?", help="只检查指定工具 id")
    parser.add_argument("--no-shot", action="store_true", help="跳过截图（只抓 DOM 与语法）")
    args = parser.parse_args()

    tool_dirs = iter_tools(args.tool_id)
    if not tool_dirs:
        print("[OK]   没有可抽检的工具（tools/ 下无工具目录）。")
        return 0

    node = find_node()
    chrome = find_chrome()

    if node is None:
        print("[WARN] 未找到 node，跳过 JS 语法抽检（安装 Node 后可开启）。")
    if chrome is None:
        print("[WARN] 未找到 Chrome / Edge，跳过无头渲染抽检。")
    if node is None and chrome is None:
        return 0

    OUT_DIR.mkdir(parents=True, exist_ok=True)
    PROBE_DIR.mkdir(parents=True, exist_ok=True)
    if PROFILE_DIR.exists():
        shutil.rmtree(PROFILE_DIR, ignore_errors=True)

    bad = 0
    for tool_dir in tool_dirs:
        if not (tool_dir / "index.html").exists():
            continue

        problems: list[str] = []
        if node:
            problems.extend(check_js(tool_dir, node))
        shot_bytes = 0
        if chrome:
            render_problems, _html, shot_bytes = check_render(tool_dir, chrome, not args.no_shot)
            problems.extend(render_problems)

        if problems:
            bad += 1
            print(f"[ERR]  {tool_dir.name}")
            for item in problems:
                print("       - " + item)
        else:
            extra = f" · 截图 {shot_bytes // 1024}KB" if shot_bytes else ""
            stage = "语法" + ("+渲染" if chrome else "")
            print(f"[OK]   {tool_dir.name:<24} {stage} 通过{extra}")

    if bad:
        print(f"\n[FAIL] {bad} 个工具抽检未通过")
        return 1
    print(f"\n[OK]   {len(tool_dirs)} 个工具抽检通过（产物见 var/tmp/render/，可复看截图）")
    return 0


if __name__ == "__main__":
    sys.exit(main())
