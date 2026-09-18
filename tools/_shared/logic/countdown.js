/*!
 * countdown.js — 倒计时引擎（漂移校正，基于 Date.now 绝对时间）
 *
 * 共享逻辑源。改这里，然后跑：python scripts/sync_shared.py
 * 依赖：无。
 * 全局：ET.Countdown
 *
 * 用法：
 *   var timer = ET.Countdown.create({
 *     onTick: function (remaining, total) { … },   // 毫秒，remaining ≤ 0 表示结束
 *     onFinish: function () { … },
 *     onState: function (state) { … }              // 'idle' | 'running' | 'paused' | 'done'
 *   });
 *   timer.set(5 * 60 * 1000);  // 设置总时长（idle 态下）
 *   timer.start(); timer.pause(); timer.resume(); timer.toggle(); timer.reset();
 *   timer.add(30 * 1000);      // 运行中 +30s（可为负，最低减到 0）
 *   timer.state; timer.remaining; timer.total;
 *
 * 特性：
 * - 用 endAt 绝对时间计算剩余，后台标签页休眠后回来不漂移
 * - 触发粒度 100ms，回调里的 remaining 为真实剩余毫秒
 */
(function (global) {
    'use strict';

    var TICK_MS = 100;

    function create(options) {
        var opt = options || {};
        var state = 'idle';          /* idle / running / paused / done */
        var total = 0;               /* 总时长 ms */
        var endAt = 0;               /* 结束绝对时间戳 */
        var remainAtPause = 0;       /* 暂停时刻的剩余 */
        var intervalId = 0;
        var finished = false;

        var api = {};

        function callTick(remaining) {
            if (opt.onTick) { opt.onTick(remaining, total); }
        }

        function callState(s) {
            state = s;
            api.state = s;
            if (opt.onState) { opt.onState(s); }
        }

        function stopLoop() {
            if (intervalId) {
                clearInterval(intervalId);
                intervalId = 0;
            }
        }

        function finish() {
            stopLoop();
            remainAtPause = 0;
            if (!finished) {
                finished = true;
                callState('done');
                if (opt.onFinish) { opt.onFinish(); }
            }
            callTick(0);
        }

        function loop() {
            var remaining = endAt - Date.now();
            if (remaining <= 0) {
                finish();
            } else {
                callTick(remaining);
            }
        }

        /* ---------- 公开方法 ---------- */

        /** 设置总时长（仅在非运行态有效） */
        api.set = function (ms) {
            if (state === 'running') { return; }
            total = Math.max(0, Math.round(ms));
            remainAtPause = total;
            finished = false;
            callState(state === 'done' ? 'idle' : state === 'idle' ? 'idle' : 'idle');
            callTick(total);
        };

        api.start = function () {
            if (state === 'running' || total <= 0) { return; }
            finished = false;
            endAt = Date.now() + (state === 'paused' ? remainAtPause : total);
            callState('running');
            loop();
            stopLoop();
            intervalId = setInterval(loop, TICK_MS);
        };

        api.resume = api.start;

        api.pause = function () {
            if (state !== 'running') { return; }
            remainAtPause = Math.max(0, endAt - Date.now());
            stopLoop();
            callState('paused');
            callTick(remainAtPause);
        };

        api.toggle = function () {
            if (state === 'running') { api.pause(); } else { api.start(); }
        };

        api.reset = function () {
            stopLoop();
            remainAtPause = total;
            finished = false;
            callState('idle');
            callTick(total);
        };

        /** 运行/暂停态增减时间；delta 毫秒（可负）。运行中直接推 endAt。 */
        api.add = function (delta) {
            delta = Math.round(delta || 0);
            if (state === 'running') {
                endAt += delta;
                var remaining = endAt - Date.now();
                if (remaining <= 0) { finish(); }
                else { callTick(remaining); }
            } else if (state === 'paused' || state === 'idle') {
                var base = state === 'paused' ? remainAtPause : total;
                var next = Math.max(0, base + delta);
                if (state === 'paused') { remainAtPause = next; }
                else { total = next; remainAtPause = next; }
                callTick(next);
            }
            if (state === 'idle' && total === 0 && delta > 0) { total = delta; remainAtPause = delta; }
        };

        Object.defineProperty(api, 'remaining', {
            get: function () {
                if (state === 'running') { return Math.max(0, endAt - Date.now()); }
                if (state === 'paused') { return remainAtPause; }
                if (state === 'done') { return 0; }
                return total;
            }
        });
        Object.defineProperty(api, 'total', { get: function () { return total; } });

        api.state = state;

        return api;
    }

    /* ---------- 展示格式化（共享给各工具保持一致） ---------- */

    /** 毫秒 → {h, m, s, cs}（cs 为百分秒） */
    function breakdown(ms) {
        var t = Math.max(0, Math.ceil(ms / 10) * 10); /* 向上取整到 0.1s，显示 0:00 前不会提前 */
        var cs = Math.floor((t % 1000) / 10);
        var sec = Math.floor(t / 1000);
        var s = sec % 60;
        var m = Math.floor(sec / 60) % 60;
        var h = Math.floor(sec / 3600);
        return { h: h, m: m, s: s, cs: cs };
    }

    /** 毫秒 → "MM:SS" 或 "H:MM:SS" */
    function format(ms) {
        var b = breakdown(ms);
        var mm = (b.m < 10 ? '0' : '') + b.m;
        var ss = (b.s < 10 ? '0' : '') + b.s;
        if (b.h > 0) { return b.h + ':' + mm + ':' + ss; }
        return mm + ':' + ss;
    }

    global.ET = global.ET || {};
    global.ET.Countdown = { create: create, breakdown: breakdown, format: format };
})(window);
