# 导数图像 更新记录

遵循 [Keep a Changelog](https://keepachangelog.com/zh-CN/1.1.0/) 与[语义化版本](https://semver.org/lang/zh-CN/)。

## [Unreleased]

## [1.0.1] - 2026-09-20

### 修复

- 深色模式识别错误：`isDark()` 原读 `document.body.classList.contains('et-dark')`，
  而主题实际写在 `<html data-et-theme>` 上，导致深色下 Canvas 网格 / 坐标轴 / 刻度 / 标注
  仍用浅色配色（近黑压在深色底上）；改为读 `<html data-et-theme>`，并新增 MutationObserver
  在切换深浅色后自动重绘（Canvas 颜色不随 CSS 更新）

## [1.0.0] - 2026-09-19

### 新增

- 输入表达式实时绘制 f 与 f-prime 图像，单调区间着色、极值点标注、悬停切线与斜率读数，白名单表达式解析器
