# 工具模板示例 更新记录

遵循 [Keep a Changelog](https://keepachangelog.com/zh-CN/1.1.0/) 与[语义化版本](https://semver.org/lang/zh-CN/)。

## [Unreleased]

## [1.0.0] - 2026-09-18

### 变更

由 P0 期的 `hello-keetools`（仅验证扫描 / ET-META / 同步链路的空壳）改造为
**可运行的新工具开发活文档**：

- 完整的 `et-chrome` 外壳：顶栏、状态胶囊、明暗主题、全屏、音效开关、帮助弹窗、页脚回流
- 演示标准能力：设置抽屉（`opt.settings`）、toast、音效 `beep`、`modal`、`localStorage` 持久化
- 演示两个视图切换（卡片 / 列表）与独立 accent 配色
- 内置「开发四步」清单与关键代码片段（`ET.Chrome.mount` 参数、ET:INLINE 标记写法）
- 翻页笔（PageDown / PageUp）与空格键快捷键示范
- `stats_enabled` 置为 `false`：示例本身不参与统计
