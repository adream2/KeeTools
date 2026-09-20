/*!
 * level-chart.js — 音量趋势图（共享逻辑源）
 *
 * 共享逻辑源。改这里，然后跑：python scripts/sync_shared.py
 * 禁止手工编辑工具 HTML 内 ET:INLINE 之间的内容。
 *
 * 职责边界：把「环形缓冲 + 自适应坐标 + 阈值线 + 分段着色」画成一张
 * 单轴折线图，供噪音计 / 早读检测复用（两工具的判定逻辑各不相同）。
 *
 * 用法：
 *   var chart = ET.LevelChart.attach(canvasEl, {
 *       capacity: 240,        // 采样点容量（满了覆盖最早的点）
 *       min: 30, max: 100,    // 纵轴固定范围（dB）
 *       threshold: 65,        // 阈值参考线；null = 不画
 *       thresholdHigh: 85,    // 可选，上限参考线（早读检测的"太吵"线）
 *       accent: '#2563eb',    // 曲线颜色
 *       theme: function () { return 'dark' | 'light'; }   // 可选，缺省读 <html data-et-theme>
 *   });
 *   chart.push(62.4, 'ok');   // tone: ok | warn | bad（缺省用 accent）
 *   chart.setThreshold(60);   // 单值 = 只画一条；setThreshold(50, 85) = 画上限
 *   chart.reset();
 *   chart.draw();             // 尺寸 / 主题变化后手动重画
 *
 * 兼容性：ES5 + Canvas 2D（老内核无 DPR 时退化为 1x，不影响可用性）。
 * 依赖：无。全局：window.ET.LevelChart
 */
(function (global) {
    'use strict';

    var ET = global.ET || {};

    var TONES = {
        ok: '#16a34a',
        warn: '#d97706',
        bad: '#dc2626'
    };

    var THEME_COLORS = {
        light: { line: 'rgba(15,23,42,.14)', text: '#6f6d62', grid: 'rgba(15,23,42,.07)' },
        dark: { line: 'rgba(148,163,184,.28)', text: '#a8a79b', grid: 'rgba(148,163,184,.12)' }
    };

    function currentTheme() {
        var attr = document.documentElement.getAttribute('data-et-theme');
        return attr === 'dark' ? 'dark' : 'light';
    }

    /** 画一条水平参考虚线（value 为 null 时跳过） */
    function drawLine(ctx, value, color, yOf, padLeft, w, dpr) {
        if (value === null || typeof value !== 'number') { return; }

        ctx.save();
        ctx.strokeStyle = color;
        ctx.lineWidth = 1.4 * dpr;
        if (ctx.setLineDash) { ctx.setLineDash([6 * dpr, 4 * dpr]); }
        ctx.beginPath();
        ctx.moveTo(padLeft, yOf(value));
        ctx.lineTo(w - 6 * dpr, yOf(value));
        ctx.stroke();
        ctx.restore();
    }

    function attach(canvas, options) {
        var opt = options || {};
        var capacity = Math.max(2, opt.capacity || 240);
        var range = { min: typeof opt.min === 'number' ? opt.min : 30, max: typeof opt.max === 'number' ? opt.max : 100 };
        var threshold = typeof opt.threshold === 'number' ? opt.threshold : null;
        var thresholdHigh = typeof opt.thresholdHigh === 'number' ? opt.thresholdHigh : null;
        var accent = opt.accent || '#2563eb';
        var themeOf = typeof opt.theme === 'function' ? opt.theme : currentTheme;

        var values = [];
        var tones = [];
        var head = 0;
        var count = 0;

        function push(value, tone) {
            var v = typeof value === 'number' && isFinite(value) ? value : range.min;
            values[head] = v;
            tones[head] = tone || '';
            head = (head + 1) % capacity;
            if (count < capacity) { count++; }
            draw();
        }

        function ordered() {
            var out = [];
            var start = count < capacity ? 0 : head;
            for (var i = 0; i < count; i++) {
                var idx = (start + i) % capacity;
                out.push({ v: values[idx], tone: tones[idx] || '' });
            }
            return out;
        }

        function fit() {
            var dpr = global.devicePixelRatio || 1;
            var cssW = canvas.clientWidth || canvas.parentNode && canvas.parentNode.clientWidth || 600;
            var cssH = canvas.clientHeight || 120;
            var w = Math.max(120, Math.round(cssW * dpr));
            var h = Math.max(60, Math.round(cssH * dpr));
            if (canvas.width !== w || canvas.height !== h) {
                canvas.width = w;
                canvas.height = h;
            }
            return { w: w, h: h, dpr: dpr };
        }

        function draw() {
            if (!canvas || typeof canvas.getContext !== 'function') { return; }
            var ctx = canvas.getContext('2d');
            if (!ctx) { return; }

            var size = fit();
            var w = size.w;
            var h = size.h;
            var dpr = size.dpr;
            var theme = themeOf() === 'dark' ? THEME_COLORS.dark : THEME_COLORS.light;

            ctx.clearRect(0, 0, w, h);

            var padTop = 8 * dpr;
            var padBottom = 16 * dpr;
            var padLeft = 30 * dpr;
            var plotW = Math.max(10, w - padLeft - 6 * dpr);
            var plotH = Math.max(10, h - padTop - padBottom);

            function yOf(v) {
                var t = (v - range.min) / Math.max(1e-6, range.max - range.min);
                if (t < 0) { t = 0; }
                if (t > 1) { t = 1; }
                return padTop + (1 - t) * plotH;
            }

            /* 横轴网格（4 条）+ 刻度值 */
            ctx.font = (9 * dpr) + 'px ' + '-apple-system, "Segoe UI", "Microsoft YaHei", sans-serif';
            ctx.textBaseline = 'middle';
            ctx.textAlign = 'right';
            for (var g = 0; g <= 4; g++) {
                var val = range.min + (range.max - range.min) * (g / 4);
                var y = yOf(val);
                ctx.strokeStyle = theme.grid;
                ctx.lineWidth = 1 * dpr;
                ctx.beginPath();
                ctx.moveTo(padLeft, y);
                ctx.lineTo(w - 6 * dpr, y);
                ctx.stroke();
                ctx.fillStyle = theme.text;
                ctx.fillText(String(Math.round(val)), padLeft - 5 * dpr, y);
            }

            /* 参考线：下限定为 warn、上限定为 bad */
            drawLine(ctx, threshold, TONES.warn, yOf, padLeft, w, dpr);
            drawLine(ctx, thresholdHigh, TONES.bad, yOf, padLeft, w, dpr);

            var points = ordered();
            if (points.length >= 2) {
                var stepX = plotW / (capacity - 1);
                /* 尽量右对齐：最新点贴右边 */
                var offsetX = padLeft + plotW - stepX * (points.length - 1);

                ctx.lineWidth = 2 * dpr;
                ctx.lineJoin = 'round';
                ctx.lineCap = 'round';
                for (var i = 1; i < points.length; i++) {
                    ctx.strokeStyle = TONES[points[i].tone] || accent;
                    ctx.beginPath();
                    ctx.moveTo(offsetX + stepX * (i - 1), yOf(points[i - 1].v));
                    ctx.lineTo(offsetX + stepX * i, yOf(points[i].v));
                    ctx.stroke();
                }
            }

            ctx.textAlign = 'left';
            ctx.fillStyle = theme.text;
            ctx.textBaseline = 'bottom';
            ctx.fillText('过去 ' + Math.round(count * 0.25) + ' 秒', padLeft, h);
        }

        function reset() {
            values = [];
            tones = [];
            head = 0;
            count = 0;
            draw();
        }

        function setThreshold(value, high) {
            threshold = typeof value === 'number' ? value : null;
            thresholdHigh = typeof high === 'number' ? high : null;
            draw();
        }

        function setRange(min, max) {
            if (typeof min === 'number') { range.min = min; }
            if (typeof max === 'number') { range.max = max; }
            draw();
        }

        draw();

        return {
            push: push,
            draw: draw,
            reset: reset,
            setThreshold: setThreshold,
            setRange: setRange,
            count: function () { return count; }
        };
    }

    ET.LevelChart = { attach: attach, TONES: TONES };
    global.ET = ET;
})(window);
