# 第三方资源许可证登记

> 本文件由 `scripts/gen_licenses.py` 从 **`src/data/third-party.json`** 自动生成，
> **请勿手工编辑**（登记 / 修改请改 JSON 后重新生成）。
> 前台展示版见 `/about`（同样由 JSON 自动渲染）。

---

## 登记规则

新增任何第三方资源（图标 / 音频 / 数据 / 字体 / JS 库）时，必须：

1. 确认许可证允许商用
2. 在 src/data/third-party.json 新增条目
3. 若许可证要求署名（如 CC-BY），在产出的页面中保留声明
4. 若来源为 npm/CDN，需一并下载到 assets-src/ 或 tools/_shared/ 后内联
5. 跑 python scripts/gen_licenses.py 重新生成 THIRD-PARTY-LICENSES.md

**禁令**：

- ❌ 不得引用运行时外部 URL
- ❌ 不得使用许可证不明或禁止商用的资源
- ❌ 不得使用带商标限制的图标集（如 logos:、simple-icons:）用于商业界面

---


## 图标

| 资源 | 来源 | 许可证 | 商用 | 署名要求 | 用途 |
|---|---|---|---|---|---|
| Lucide Icons | https://lucide.dev | ISC | ✅ | 无需（建议保留） | 网站 UI 图标与工具内图标，经 Iconify 按需子集化后打包为本地 SVG sprite。 |
| Tabler Icons | https://tabler.io/icons | MIT | ✅ | 无需 | 少量网站 UI 图标，同样本地子集化打包。 |

> 已内置图标集明细：Lucide 以 `assets-src/icons/lucide.json`（Iconify JSON 子集，46 图标 + 4 别名）入库，获取日期 2026-09-18。

> 工具图标：位于 `tools/_shared/icons/icons.svg`（Lucide 子集手工内联，约 40 个 symbol，ISC 许可证，currentColor 跟随主题），经 `sync_shared.py` 内联进各工具 HTML，与网站 sprite 相互独立。

> 若后续引入 Material Symbols（Apache-2.0），需在本文件登记并在页面保留 NOTICE。

---


## 字体


> 全程使用**系统字体栈**（定义于 `assets-src/css/tokens.css`），不引入任何字体文件。

---


## JavaScript 库


> 全程使用原生 JS，不引入任何 JS 库。

---


## CSS 框架


> 自写 CSS（tokens / base / components / site 四层）。

---


## 数据与素材

| 资源 | 来源 | 许可证 | 商用 | 署名要求 | 用途 |
|---|---|---|---|---|---|
| 汉语拼音音节真人录音（183 个 mp3） | https://github.com/hugolpz/audio-cmn（18k-abr/syllabs/），Chen Wang 录制；原音源 shtooka/cmn | CC BY-SA | ✅ | 须署名 | tools/pinyin-chart/ 点读音频（base64 内嵌）。 |
| 汉字笔画中位线数据（210 字） | npm hanzi-writer-data@2.0.1，派生自 Make Me a Hanzi | Arphic Public License | ✅ | 须保留许可证文本与声明 | tools/stroke-order/ 逐笔书写动画（内联，运行时不联网）。 |
| 汉字拼音数据（GB2312 一级常用字 3755 字） | https://github.com/mozillazg/pinyin-data（pinyin.txt，整理自 Unicode Unihan kMandarin 等） | MIT | ✅ | 无需（建议保留） | tools/pinyin-to-words/ 自动注音：由 scripts/gen_hanzi_pinyin.py 抽取常用字集精简后内联，运行时不联网。 |

> 署名义务：已在 `tools/pinyin-chart/README.md` 与工具内帮助弹窗注明「音频：Chen Wang 录制（audio-cmn，CC BY-SA）」。CC BY-SA 具有相同方式共享义务，音频以独立 mp3 形式内嵌、未做演绎修改（仅格式/码率经上游转换），在此登记以履行披露。

> `hanzi-writer-data` 的笔画中位线派生自 Make Me a Hanzi（Arphic Public License），工具内仅保留每字各笔的中位线采样点（medians），未含原始 SVG 轮廓；已在 `tools/stroke-order/index.html` 的帮助弹窗与设置抽屉注明来源与许可证。

---


## 审计记录

| 日期 | 操作 | 说明 |
|---|---|---|
| 2026-09-18 | 建立登记表 | 初始登记，当前仅 Lucide + Tabler |
| 2026-09-18 | P2 工具图标集 | tools/_shared/icons/icons.svg 内联 Lucide 子集（ISC）约 40 符号 |
| 2026-09-19 | 关于页上线 | /about 面向访客展示署名版 |
| 2026-09-19 | 登记自动化 | 唯一真源迁移至 src/data/third-party.json（原 assets-src/third-party.json，2026-09-20 迁入运行时目录），/about 自动渲染，THIRD-PARTY-LICENSES.md 改为脚本生成 |
| 2026-09-21 | 拼音数据 | 新增 mozillazg/pinyin-data（MIT）衍生的 GB2312 一级常用字拼音表，用于 tools/pinyin-to-words 自动注音，由 scripts/gen_hanzi_pinyin.py 生成 |
