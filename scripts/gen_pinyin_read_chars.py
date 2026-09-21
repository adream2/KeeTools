#!/usr/bin/env python3
"""gen_pinyin_read_chars.py — 生成 pinyin-wheel 的「同音字朗读表」

从 tools/pinyin-to-words/index.html 的 PYW_DICT 内嵌数据（GB2312 一级 3755 字拼音表，
由 gen_hanzi_pinyin.py 生成）反查：拼音(含调) → 第一个常用汉字。
枚举 pinyin-wheel 的 声母×韵母×{轻,1,2,3,4} 声调 组合，把能读的音节写成
`var PW_READ = {"mā":"妈", ...}` 注入 tools/pinyin-wheel/index.html 的 PWR 标记块。

课堂价值：中文语音引擎读拉丁拼音串发音不可靠，读同音汉字则完全正确。

用法：
    python scripts/gen_pinyin_read_chars.py            # 生成 / 更新
    python scripts/gen_pinyin_read_chars.py --check    # 门禁：校验产物为最新
"""
import re
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
SRC_TOOL = ROOT / "tools" / "pinyin-to-words" / "index.html"
DST_TOOL = ROOT / "tools" / "pinyin-wheel" / "index.html"

BEGIN = "/* PWR:BEGIN 由 scripts/gen_pinyin_read_chars.py 生成，请勿手工编辑 */"
END = "/* PWR:END */"

# 与 tools/pinyin-wheel/index.html 保持一致
INITIALS = ["—", "b", "p", "m", "f", "d", "t", "n", "l", "g", "k", "h",
            "j", "q", "x", "zh", "ch", "sh", "r", "z", "c", "s", "y", "w"]
FINALS = ["a", "o", "e", "i", "u", "ü", "ai", "ei", "ui", "ao", "ou", "iu",
          "ie", "üe", "er", "an", "en", "in", "un", "ün", "ang", "eng", "ing", "ong"]

MARKS = {
    "a": "āáǎà", "o": "ōóǒò", "e": "ēéěè", "i": "īíǐì",
    "u": "ūúǔù", "ü": "ǖǘǚǜ",
}


def mark_vowel(syll: str, tone: int) -> str:
    """按 a→o→e→(i/u/ü 取最后) 规则给音节标调（与工具内 markedSyllable 一致）"""
    if tone <= 0:
        return syll
    idx = -1
    for v in ("a", "o", "e"):
        idx = syll.find(v)
        if idx >= 0:
            break
    if idx < 0:
        cands = [i for v in ("i", "u", "ü") for i in [syll.find(v)] if i >= 0]
        if not cands:
            return syll
        idx = max(cands)
    ch = syll[idx]
    if ch not in MARKS:
        return syll
    return syll[:idx] + MARKS[ch][tone - 1] + syll[idx + 1:]


def load_dict() -> dict:
    """解析 PYW_DICT：「字+拼音」连续串 → {拼音(含调或轻声): 首个常用字}"""
    src = SRC_TOOL.read_text(encoding="utf-8")
    m = re.search(r'PYW_DICT = "([^"]+)"', src)
    if not m:
        raise SystemExit("[FAIL] pinyin-to-words 中找不到 PYW_DICT 数据")
    data = m.group(1)
    rev: dict = {}
    for ch, py in re.findall(r"([\u4e00-\u9fff])((?:[a-züāáǎàēéěèīíǐìōóǒòūúǔùǖǘǚǜ]+))", data):
        if py not in rev:  # GB2312 序即常用度序，先到先得
            rev[py] = ch
    return rev


def build_table(rev: dict) -> dict:
    out: dict = {}
    for ini in INITIALS:
        for fin in FINALS:
            base = ("" if ini == "—" else ini) + fin
            for tone in range(0, 5):
                key = mark_vowel(base, tone)
                if key in rev:
                    out[key] = rev[key]
                # j/q/x/y + ü 类：规范拼写 ü→u（xué 而非 xüé），补归一化键
                if ini in ("j", "q", "x", "y") and "ü" in key and key not in rev:
                    alt = key.replace("ü", "u")
                    if alt in rev:
                        out[key] = rev[alt]
            # 轻声回退：字典基本只收带调形式，无轻声字时读任意带调同音字
            if base not in out:
                for tone in range(1, 5):
                    alt = mark_vowel(base, tone)
                    if alt in rev:
                        out[base] = rev[alt]
                        break
                    if ini in ("j", "q", "x", "y") and "ü" in alt and alt.replace("ü", "u") in rev:
                        out[base] = rev[alt.replace("ü", "u")]
                        break
    return out


def render(table: dict) -> str:
    items = ", ".join('"%s":"%s"' % (k, v) for k, v in sorted(table.items()))
    return "%s\nvar PW_READ = {%s};\n%s" % (BEGIN, items, END)


def main() -> int:
    check = "--check" in sys.argv
    rev = load_dict()
    table = build_table(rev)

    combos = len(INITIALS) * len(FINALS) * 5
    dst = DST_TOOL.read_text(encoding="utf-8")
    block = render(table)

    if check:
        if BEGIN not in dst or END not in dst:
            print("[FAIL] pinyin-wheel 缺少 PWR 标记块")
            return 1
        cur = dst[dst.index(BEGIN):dst.index(END) + len(END)]
        if cur != block:
            print("[FAIL] pinyin-wheel 朗读表不是最新，请运行 python scripts/gen_pinyin_read_chars.py")
            return 1
        print("[OK]   pinyin-wheel 朗读表为最新（%d 个音节）" % len(table))
        return 0

    if BEGIN in dst and END in dst:
        start = dst.index(BEGIN)
        end = dst.index(END) + len(END)
        dst = dst[:start] + block + dst[end:]
    else:
        anchor = "var NS = 'http://www.w3.org/2000/svg'; /* et-allow-external SVG 命名空间 */"
        if anchor not in dst:
            print("[FAIL] pinyin-wheel 找不到注入锚点")
            return 1
        dst = dst.replace(anchor, anchor + "\n\n    " + block + "\n", 1)

    DST_TOOL.write_text(dst, encoding="utf-8")
    print("[OK]   已生成 pinyin-wheel 朗读表：覆盖 %d / %d 个音节组合（%.0f%%）"
          % (len(table), combos, len(table) / combos * 100))
    return 0


if __name__ == "__main__":
    sys.exit(main())
