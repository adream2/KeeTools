# 牛顿第二定律 更新记录

遵循 [Keep a Changelog](https://keepachangelog.com/zh-CN/1.1.0/) 与[语义化版本](https://semver.org/lang/zh-CN/)。

## [Unreleased]

## [1.0.1] - 2026-09-20

### 修复

- 深色模式识别错误：`isDark()` 原读 `document.body.classList.contains('et-dark')`，
  而主题实际写在 `<html data-et-theme>` 上，导致深色下导轨 / 刻度 / 力矢量标注与
  a-F、a-1/m 图表配色错误；改为读 `<html data-et-theme>`，并新增 MutationObserver
  在切换深浅色后自动重绘（Canvas 颜色不随 CSS 更新）

## [1.0.0] - 2026-09-19

### 新增

- 气垫导轨小车 a=F/m 实验，光电门计时、数据表与 a-F / a-1/m 图像切换
