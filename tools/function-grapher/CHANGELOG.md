# 函数图像绘制器 更新记录

遵循 [Keep a Changelog](https://keepachangelog.com/zh-CN/1.1.0/) 与[语义化版本](https://semver.org/lang/zh-CN/)。

## [Unreleased]

## [1.1.0] - 2026-09-18

### 修复

- **`sin x`、`sqrt x` 等空格写法报「不认识的符号」**：解析器不再压缩空白，tokenizer 支持空白分隔的隐式函数参数（`sin x` 等价 `sin(x)`）
- **`log2(x)` 被静默解析成 `log(2)·x`**：tokenizer 现在识别「字母 + 数字」的已注册函数名（log2 / log10），同时 `x2` 仍按 x·2 处理

### 新增

- 函数库大幅扩充：cot / sec / csc、双曲函数（sinh / cosh / tanh / asinh / acosh / atanh）、cbrt、log2、frac / step / relu / clamp，以及有趣波形 gauss、sinc、square、triangle、saw；常量新增 tau / phi；别名 arcsin / arccos / arctan / tg / ctg / lg / log10
- 支持 `**` 表示乘方，全角字母数字自动转换
- 示例函数从 9 个扩到 20 个（含方波 / 三角波 / 锯齿波 / 钟形 / 取整 / x·sin(1/x)）

### 变更

- 帮助文档补齐完整函数清单与写法说明

## [1.0.0] - 2026-09-18

### 新增

- 自研解析式编译器（递归下降，编译为闭包，零依赖）：
  - 运算符 `+ - * / ^` 与括号，**省略乘号**（`2x`、`3(x+1)`、`x sin x`）
  - 函数 `sin cos tan asin acos atan sqrt abs ln log exp floor ceil round sign`，常量 `pi`、`e`
  - 简写兼容：`y=` 前缀、全角符号、`²³√π`、`|x|` 绝对值
- Canvas 绘图：自适应网格（刻度取 1 / 2 / 5 × 10ⁿ）、坐标轴与刻度数字、曲线断点处理（避免反比例函数被竖直渐近线连成一竖条）
- 交互：拖动平移、滚轮以光标为中心缩放、鼠标悬停实时坐标追踪（虚线十字线 + 读数气泡）、方向键平移、+ / − 缩放
- **关键点自动分析**：与 y 轴交点、与 x 轴交点（二分法求解）、最高 / 最低点（三点抛物线插值细化，即二次函数顶点），画布上描点标注
- 数值表：右上角浮层列出视野内整数 x 的 y 值（视野过宽时自动升步长）
- 视图工具：自适应（按函数值 5%–95% 分位裁剪，避免渐近线拉爆纵向）、重置、PNG 导出（插入课件用）
- 示例函数快捷 chips（一次 / 二次 / 反比例 / 指数 / 三角 / 三次 / 绝对值 / 根式）
- 角度制开关（`sin 30 = 0.5`），主题切换自动重绘（跟随 `data-et-theme`）
- 解析式、显示选项、角度制 localStorage 持久化
