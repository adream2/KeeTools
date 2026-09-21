# -*- coding: utf-8 -*-
"""生成 tools/pinyin-to-words 的内置「汉字 → 拼音」精简字表（PYW_DICT）。

流程：
1. 汉字拼音源（mozillazg/pinyin-data，MIT）：优先用 var/tmp/hanzi-pinyin/pinyin.txt
   本地缓存，缺失时联网下载（与 icons/prepare_source.py、gen_china_map.py 一样属低频联网脚本）。
2. 只保留 **GB2312 一级汉字（3755 个常用字）**：字集由 Python 标准库 gb2312 编解码现场算出，
   不依赖任何外部字表（避免教材编排 / 版权问题）。
3. 每个字取**第一个读音**（pinyin-data 的第一读音为最常用读音），保留声调符号。
4. 压缩成 `一yī丁dīng…` 的单一字符串（汉字字符天然作为分隔，省掉冗余分隔符），
   注入 tools/pinyin-to-words/index.html 的 PYW_DICT:BEGIN / PYW_DICT:END 之间。

用法：
    python scripts/gen_hanzi_pinyin.py            # 生成 / 更新字表
    python scripts/gen_hanzi_pinyin.py --check    # 只校验工具内字表是否与生成结果一致（门禁用）

退出码：0 = 成功 / 一致，1 = 失败 / 不一致
许可登记：src/data/third-party.json（data 分类）→ 改完跑 scripts/gen_licenses.py
"""

import argparse
import os
import re
import sys
import urllib.request
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
CACHE = ROOT / "var" / "tmp" / "hanzi-pinyin" / "pinyin.txt"
SOURCE_URL = "https://raw.githubusercontent.com/mozillazg/pinyin-data/master/pinyin.txt"
TOOL_HTML = ROOT / "tools" / "pinyin-to-words" / "index.html"

BEGIN = "/* PYW_DICT:BEGIN 由 scripts/gen_hanzi_pinyin.py 生成，请勿手工编辑 */"
END = "/* PYW_DICT:END */"

LINE_RE = re.compile(r"^U\+([0-9A-Fa-f]+):\s*([^#]*?)\s*(?:#\s*(.*))?$")


def gb2312_level1() -> list[str]:
    """GB2312 一级汉字（3755 个常用字），由编码区间现场枚举。"""
    chars = []
    for hi in range(0xB0, 0xD8):
        for lo in range(0xA1, 0xFF):
            if hi == 0xD7 and lo > 0xF9:
                continue
            try:
                chars.append(bytes([hi, lo]).decode("gb2312"))
            except UnicodeDecodeError:
                continue
    return chars


def load_source() -> str:
    if CACHE.exists():
        return CACHE.read_text(encoding="utf-8")
    print(f"[SYNC] 下载拼音数据源（首次运行，之后走本地缓存）：{SOURCE_URL}")
    CACHE.parent.mkdir(parents=True, exist_ok=True)
    with urllib.request.urlopen(SOURCE_URL, timeout=60) as resp:  # noqa: S310 (固定 https 源)
        data = resp.read().decode("utf-8")
    CACHE.write_text(data, encoding="utf-8", newline="\n")
    return data


def build_dict() -> tuple[str, int]:
    wanted = set(gb2312_level1())
    readings: dict[str, str] = {}

    for line in load_source().splitlines():
        match = LINE_RE.match(line.strip())
        if not match:
            continue
        code, readings_raw, _comment = match.groups()
        try:
            char = chr(int(code, 16))
        except ValueError:
            continue
        if char not in wanted:
            continue
        first = readings_raw.split(",")[0].strip()
        if first:
            readings[char] = first

    ordered = [c for c in gb2312_level1() if c in readings]
    # 单字符串编码：汉字字符本身即分隔符，解析端按 \u4e00-\u9fff 切分
    payload = "".join(f"{c}{readings[c]}" for c in ordered)
    return payload, len(ordered)


def write_block(html: str, payload: str) -> str:
    if BEGIN not in html or END not in html:
        raise SystemExit(f"[ERR]  未找到标记，请先确认 {TOOL_HTML.relative_to(ROOT).as_posix()} 内有 PYW_DICT 标记")
    start = html.index(BEGIN) + len(BEGIN)
    end = html.index(END)
    body = f'\nvar PYW_DICT = "{payload}";\n'
    return html[:start] + body + html[end:]


def main() -> int:
    parser = argparse.ArgumentParser(description="生成看拼音写词语工具的内置拼音字表")
    parser.add_argument("--check", action="store_true", help="只校验是否与生成结果一致")
    args = parser.parse_args()

    payload, count = build_dict()
    size_kb = len(payload.encode("utf-8")) / 1024
    print(f"[OK]   常用字表 {count} 字，压缩后 {size_kb:.1f}KB")

    if not TOOL_HTML.exists():
        print(f"[ERR]  工具文件不存在：{TOOL_HTML.relative_to(ROOT).as_posix()}")
        return 1

    html = TOOL_HTML.read_text(encoding="utf-8")
    try:
        updated = write_block(html, payload)
    except SystemExit as exc:
        print(str(exc))
        return 1

    if args.check:
        if updated != html:
            print("[FAIL] 工具内字表与生成结果不一致，请运行：python scripts/gen_hanzi_pinyin.py")
            return 1
        print("[OK]   工具内字表已是最新")
        return 0

    if updated == html:
        print("[OK]   工具内字表已是最新，无需写入")
        return 0

    TOOL_HTML.write_text(updated, encoding="utf-8", newline="\n")
    print(f"[OK]   已写入 {TOOL_HTML.relative_to(ROOT).as_posix()}")
    return 0


if __name__ == "__main__":
    sys.exit(main())
