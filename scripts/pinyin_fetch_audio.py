#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""拼音表真人音频抓取脚本（audio-cmn，CC-by-sa）。

从开源仓库 github.com/hugolpz/audio-cmn 下载音节真人录音（Chen Wang 录制），
按声调表结构内嵌到 tools/pinyin-chart/index.html 的 PINYIN_AUDIO 区块（幂等替换）。

用法：
    python scripts/pinyin_fetch_audio.py                          # 直连下载
    python scripts/pinyin_fetch_audio.py --proxy socks5://192.168.31.8:7890
    python scripts/pinyin_fetch_audio.py --check                  # 只检查区块是否已生成

音频来源与许可：
    - 音节集：Chen Wang 录制，CC-by-sa（仓库 README 明示）
    - 质量档：18k-abr（单文件 ~10KB，适合内嵌）
    - 署名要求：工具帮助/README 须注明 "音频：Chen Wang 录制（audio-cmn，CC BY-SA）"
"""

import argparse
import base64
import json
import re
import subprocess
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
TOOL_HTML = ROOT / "tools" / "pinyin-chart" / "index.html"
TMP_DIR = ROOT / "var" / "tmp" / "audio-cmn"
BASE = "https://raw.githubusercontent.com/hugolpz/audio-cmn/master/18k-abr/syllabs/"

BLOCK_RE = re.compile(r"window\.PINYIN_AUDIO = \{.*?\};", re.DOTALL)

# ------------------------------------------------------------------
# 音频映射表：label -> [一/二/三/四声的音节名（不含 cmn- 前缀与扩展名）]
# 声母无独立音频：用一声呼读（de1 等，读法即本音，不带调）。
# ü/üe/ün/un 按《汉语拼音方案》呼读映射：ü→yu、üe→yue、ün→yun、un→wen。
# ------------------------------------------------------------------
INITIAL_MAP = {
    "b": "bo1", "p": "po1", "m": "mo1", "f": "fo1",
    "d": "de1", "t": "te1", "n": "ne1", "l": "le1",
    "g": "ge1", "k": "ke1", "h": "he1",
    "j": "ji1", "q": "qi1", "x": "xi1",
    "zh": "zhi1", "ch": "chi1", "sh": "shi1", "r": "ri1",
    "z": "zi1", "c": "ci1", "s": "si1",
    "y": "yi1", "w": "wu1",
}

FINAL_MAP = {
    # 单韵母
    "a": ["a1", "a2", "a3", "a4"],
    "o": ["o1", "o2", "o3", "o4"],
    "e": ["e1", "e2", "e3", "e4"],
    "i": ["yi1", "yi2", "yi3", "yi4"],             # i 零声母写作 yi（衣）
    "u": ["wu1", "wu2", "wu3", "wu4"],             # u 零声母写作 wu（乌）
    "ü": ["yu1", "yu2", "yu3", "yu4"],
    # 复韵母
    "ai": ["ai1", "ai2", "ai3", "ai4"],
    "ei": ["ei1", "ei2", "ei3", "ei4"],
    "ui": ["wei1", "wei2", "wei3", "wei4"],
    "ao": ["ao1", "ao2", "ao3", "ao4"],
    "ou": ["ou1", "ou2", "ou3", "ou4"],
    "iu": ["you1", "you2", "you3", "you4"],
    "ie": ["ye1", "ye2", "ye3", "ye4"],
    "üe": ["yue1", "yue2", "yue3", "yue4"],
    "er": ["er1", "er2", "er3", "er4"],
    # 鼻韵母
    "an": ["an1", "an2", "an3", "an4"],
    "en": ["en1", "en2", "en3", "en4"],
    "in": ["yin1", "yin2", "yin3", "yin4"],        # in 零声母写作 yin（因）
    "un": ["wen1", "wen2", "wen3", "wen4"],
    "ün": ["yun1", "yun2", "yun3", "yun4"],
    "ang": ["ang1", "ang2", "ang3", "ang4"],
    "eng": ["eng1", "eng2", "eng3", "eng4"],
    "ing": ["ying1", "ying2", "ying3", "ying4"],   # ing 零声母写作 ying（英）
    "ong": ["weng1", "weng2", "weng3", "weng4"],   # ong 呼读 ≈ 翁（weng）
    # 整体认读音节
    "zhi": ["zhi1", "zhi2", "zhi3", "zhi4"],
    "chi": ["chi1", "chi2", "chi3", "chi4"],
    "shi": ["shi1", "shi2", "shi3", "shi4"],
    "ri": ["ri1", "ri2", "ri3", "ri4"],
    "zi": ["zi1", "zi2", "zi3", "zi4"],
    "ci": ["ci1", "ci2", "ci3", "ci4"],
    "si": ["si1", "si2", "si3", "si4"],
    "yi": ["yi1", "yi2", "yi3", "yi4"],
    "wu": ["wu1", "wu2", "wu3", "wu4"],
    "yu": ["yu1", "yu2", "yu3", "yu4"],
    "ye": ["ye1", "ye2", "ye3", "ye4"],
    "yue": ["yue1", "yue2", "yue3", "yue4"],
    "yuan": ["yuan1", "yuan2", "yuan3", "yuan4"],
    "yin": ["yin1", "yin2", "yin3", "yin4"],
    "yun": ["yun1", "yun2", "yun3", "yun4"],
    "ying": ["ying1", "ying2", "ying3", "ying4"],
}


def download(name: str, proxy: str | None) -> bytes | None:
    """curl 下载单个 mp3（经代理），404/失败返回 None。"""
    url = BASE + "cmn-" + name + ".mp3"
    out = TMP_DIR / (name + ".mp3")
    if out.exists() and out.stat().st_size > 500:
        return out.read_bytes()  # 断点复用
    cmd = ["curl.exe", "-s", "--max-time", "30"]
    if proxy:
        cmd += ["--socks5-hostname", proxy.split("://", 1)[-1]]
    cmd += ["-o", str(out), url]
    result = subprocess.run(cmd, capture_output=True)
    if result.returncode != 0 or not out.exists() or out.stat().st_size < 500:
        out.unlink(missing_ok=True)
        return None
    return out.read_bytes()


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--proxy", default=None, help="socks5 代理，如 socks5://192.168.31.8:7890")
    parser.add_argument("--check", action="store_true")
    args = parser.parse_args()

    html = TOOL_HTML.read_text(encoding="utf-8")

    if args.check:
        if "window.PINYIN_AUDIO" in html:
            print("[OK]   拼音音频区块已生成")
            return 0
        print("[FAIL] 拼音音频区块缺失，请运行：python scripts/pinyin_fetch_audio.py --proxy ...")
        return 1

    TMP_DIR.mkdir(parents=True, exist_ok=True)
    audio: dict[str, dict[str, str]] = {}
    missing: list[str] = []
    total = 0

    jobs: list[tuple[str, int, str]] = []
    for label, name in INITIAL_MAP.items():
        jobs.append((label, 1, name))
    for label, names in FINAL_MAP.items():
        for idx, name in enumerate(names, start=1):
            jobs.append((label, idx, name))

    print(f"[..]   共 {len(jobs)} 个音频待下载……")
    for i, (label, tone, name) in enumerate(jobs, start=1):
        data = download(name, args.proxy)
        if data is None:
            missing.append(f"{label}{tone}({name})")
            continue
        total += len(data)
        audio.setdefault(label, {})[str(tone)] = (
            "data:audio/mpeg;base64," + base64.b64encode(data).decode("ascii")
        )
        if i % 20 == 0 or i == len(jobs):
            print(f"  [{i}/{len(jobs)}] 已下载 {total / 1024:.0f} KB")

    block = "window.PINYIN_AUDIO = " + json.dumps(audio, ensure_ascii=False, separators=(",", ":")) + ";"
    header = (
        "<script>\n"
        "/* ==================================================================\n"
        " * PINYIN_AUDIO — 真人标准读音音频（audio-cmn，CC BY-SA，Chen Wang 录制）\n"
        " * 结构：{ 音节: { \"1\"..\"4\": data:audio/mpeg;base64,... } }（声母仅 \"1\"）\n"
        " * 由 scripts/pinyin_fetch_audio.py 生成，构建期产物入库，运行时完全离线。\n"
        " * ================================================================== */\n"
    )
    new_block = header + block + "\n</script>"

    if "window.PINYIN_AUDIO = {" in html:
        html = BLOCK_RE.sub(lambda _: header + block + "\n", html)
        print("[OK]   已替换既有音频区块")
    else:
        anchor = "<script>\n/* ==================================================================\n * PINYIN_DATA — 内置数据"
        if anchor not in html:
            raise RuntimeError("未找到音频区块插入锚点")
        html = html.replace(anchor, new_block + "\n\n" + anchor)
        print("[OK]   已插入音频区块")

    TOOL_HTML.write_text(html, encoding="utf-8", newline="\n")
    print(f"[OK]   完成：{sum(len(v) for v in audio.values())} 个音频 / {len(audio)} 个音节，"
          f"mp3 合计 {total / 1024:.0f} KB（base64 后约 {int(total * 1.37 / 1024)} KB）")
    if missing:
        print(f"[WARN] 缺失 {len(missing)} 个：{', '.join(missing[:10])}"
              + ("…" if len(missing) > 10 else ""))
    return 0


if __name__ == "__main__":
    sys.exit(main())
