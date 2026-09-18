# 汉语拼音表

一年级拼音点读表：点击任意拼音朗读，适合大屏领读与自查。

## 数据结构（可整体替换）

本工具是**通用渲染器**，内置数据在 `index.html` 源码的 `PINYIN_DATA` 处，结构：

```json
{
  "sections": [
    {
      "id": "shengmu",
      "title": "声母",
      "items": [
        { "label": "b", "example": "玻", "speak": "bo" }
      ]
    }
  ]
}
```

| 字段 | 必填 | 说明 |
|---|---|---|
| `label` | ✅ | 卡片主显示文字 |
| `example` | ❌ | 例字 / 注释（卡片下方小字） |
| `speak` | ❌ | 朗读内容，缺省读 `label` |
| `id` / `title` | ✅ | 分节标识与标题 |

替换原则：条目总数超过 500 条时应改配独立数据文件，并登记 `docs/工具例外清单.md`（`single_file: false`）。

## 朗读说明

**真人标准录音**：全部读音来自开源项目 [audio-cmn](https://github.com/hugolpz/audio-cmn)（Chen Wang 录制，CC BY-SA 许可），构建期内嵌为 base64 mp3（约 500KB），完全离线、零外部依赖。

- 点卡片 / 列表行读**一声**；韵母与整体认读卡片上有 **ā á ǎ à 四声按钮**，点哪个读哪个
- 分节连读按一声顺序朗读
- 重生成/替换音频：`python scripts/pinyin_fetch_audio.py --proxy socks5://…`

### 音频许可（CC BY-SA）

音节录音：Chen Wang 录制，经 [audio-cmn](https://github.com/hugolpz/audio-cmn) 项目整理（CC BY-SA）。
依据许可要求在本页完成署名；衍生分发需同样注明作者与许可。

本工具包含**模块化**使用统计块（源码中标注 `et-stats`），删除该块即可完全不联网，见下文。

## 离线名单（推荐）

把名单存成 `roster.js` 放在本工具**同目录**，下次双击打开自动加载、免反复设置（适合从 PPT / 收藏夹直接打开）：

```js
window.ET_ROSTER = "张三
李四,2";
```

也可以写成数组：`window.ET_ROSTER = ["张三", "李四"]` 或 `[{"name":"张三","weight":2}]`。
在线打开时还支持 `roster.txt`（一行一个，UTF-8）。离线名单**优先于**本机保存的数据。

## 关于统计模块

本工具包含一个**模块化**使用统计块（源码中标注 `et-stats`）：仅在联网条件下向站点上报一次匿名使用量（`use_offline`），不采集任何内容数据；未配置站点地址时完全静默。
**删除该 `<script>` 块即可让工具完全不联网**，其余功能不受任何影响。

## 翻页笔 / 激光笔

翻页笔的上下页键（PageDown / PageUp）即本工具的主操作键，无需鼠标即可完成核心操作，适合教室大屏远距离操控。
