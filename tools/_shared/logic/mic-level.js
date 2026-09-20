/*!
 * mic-level.js — 麦克风电平采集（共享逻辑源）
 *
 * 共享逻辑源。改这里，然后跑：python scripts/sync_shared.py
 * 禁止手工编辑工具 HTML 内 ET:INLINE 之间的内容。
 *
 * 职责边界：只做「能力检测 → 取麦 → 逐帧算 RMS / 峰值 → 回调 / 状态回调」，
 * 不含任何 UI、阈值判定与文案渲染（噪音计 / 早读检测共用的底座）。
 *
 * 用法：
 *   var mic = ET.Mic.create();
 *   mic.open({
 *       fftSize: 2048,          // 可选，默认 2048
 *       smooth: 0.8,            // 可选，0–0.98，EMA 平滑系数（越大越稳、越迟钝）
 *       offset: 90,             // 可选，dBFS → 近似 dB SPL 的校准偏移（工具可让用户校准）
 *       onState: function (state) {},   // 见下
 *       onFrame: function (f) {}        // {rms, dbfs, db, smoothed, peak, ts}
 *   });
 *   mic.close();
 *
 * onState 取值：
 *   unsupported 无 getUserMedia / 无 AudioContext
 *   insecure    非安全上下文（http 且非 localhost）→ 浏览器一定禁止取麦
 *   pending     已发起授权请求（首次会弹浏览器权限框）
 *   running     正在采集
 *   denied      用户拒绝 / 浏览器策略拒绝（NotAllowedError、SecurityError）
 *   nodevice    找不到音频输入设备（NotFoundError）
 *   busy        设备被其它程序占用（NotReadableError）
 *   error       其它异常（detail 里带原始错误）
 *   closed      已释放
 *
 * 关键事实（踩坑记录，工具侧须据此做引导）：
 *   1. 取麦的前提是「安全上下文」：https 与 localhost 稳定可用，http（非 localhost）必然被拒。
 *      file:// 的情况各浏览器不一致（Chrome 视其为可信来源、能否成功取决于权限与策略；
 *      Safari 等会直接拒绝），**因此不要预先断言"本地文件一定不行"**：本片段只在
 *      http 非 localhost 时预判为 insecure，其余情况一律真去请求，失败再按错误名分类，
 *      并用 ET.Mic.isLocalFile() 提示"可改用在线体验 / HTTPS"。
 *      2026-09-20 实测：headless 下 file:// 的取麦请求会挂起不落地，无法据此下结论——
 *      文案必须按上面这种"区间表述"写，不能写死。
 *   2. 想量真实音量必须关掉浏览器的自动增益/降噪（echoCancellation / noiseSuppression /
 *      autoGainControl 全设 false），否则测得的是被"美化"过的声音。
 *   3. 释放时务必 track.stop() + AudioContext.close()，否则标签页一直显示"正在录音"。
 *   4. dB SPL 无法脱离设备标定，db 只是「dBFS + offset」的近似值，工具文案不得
 *      宣称绝对准确，应提供校准入口。
 *
 * 兼容性：ES5（老内核可运行）；Promise 由浏览器提供，本片段只做能力检测。
 * 依赖：无。全局：window.ET.Mic
 */
(function (global) {
    'use strict';

    var ET = global.ET || {};
    var DEFAULT_FFT = 2048;
    var MIN_DBFS = -100;

    /** 状态 → 面向教师的通用提示（工具可覆盖文案，但缺省即可用） */
    var HINTS = {
        unsupported: {
            title: '当前浏览器不支持麦克风采集',
            tip: '请改用 Chrome / Edge 90+ 或 Safari 14+ 打开本工具。'
        },
        insecure: {
            title: '当前打开方式禁止使用麦克风',
            tip: '浏览器只允许安全上下文取麦：https 与 localhost 稳定可用，http 页面会被直接禁止。' +
                '请改用「在线体验」页、或把工具放到本地服务器 / 校园网 HTTPS 环境再打开。'
        },
        pending: {
            title: '等待麦克风授权',
            tip: '浏览器会弹出权限询问，请选择「允许」。'
        },
        denied: {
            title: '麦克风权限被拒绝',
            tip: '点地址栏左侧的权限图标（或站点设置 → 麦克风）改成「允许」后重新开始。' +
                '若你是直接双击本地文件（file://）打开的，Safari 等浏览器会直接拒绝取麦：' +
                '这种情况请改用「在线体验」页，或把工具放到本地服务器 / HTTPS 环境。'
        },
        nodevice: {
            title: '没有找到麦克风设备',
            tip: '请插入麦克风或检查系统声音设置里的输入设备，再重新开始。'
        },
        busy: {
            title: '麦克风被占用',
            tip: '请关闭正在录音 / 开会的程序（或其它标签页），再重新开始。'
        },
        error: {
            title: '麦克风启动失败',
            tip: '请刷新页面重试；若仍失败，换 Chrome / Edge 打开。'
        },
        closed: {
            title: '已停止采集',
            tip: ''
        }
    };

    function getMediaDevices() {
        if (navigator.mediaDevices && typeof navigator.mediaDevices.getUserMedia === 'function') {
            return navigator.mediaDevices;
        }
        return null;
    }

    function legacyGetUserMedia() {
        var fn = navigator.getUserMedia || navigator.webkitGetUserMedia || navigator.mozGetUserMedia;
        return typeof fn === 'function' ? fn : null;
    }

    function supported() {
        return !!(getMediaDevices() || legacyGetUserMedia());
    }

    function hasAudioContext() {
        return !!(global.AudioContext || global.webkitAudioContext);
    }

    /**
     * 是否安全上下文（https / localhost）——http 页面浏览器一定不给取麦。
     * 注意：Chrome 把 file:// 也视为可信来源，所以这里**不**把 file:// 判为不安全，
     * 避免误报；本地文件的失败交由 classify() 按真实错误名处理。
     */
    function isSecure() {
        if (location.protocol === 'http:') {
            var host = location.hostname;
            return host === 'localhost' || host === '127.0.0.1' || host === '[::1]';
        }
        if (typeof global.isSecureContext === 'boolean') {
            return global.isSecureContext;
        }
        return location.protocol === 'https:';
    }

    /** 是否以本地文件方式打开（file://）——用于提示"可改用在线体验" */
    function isLocalFile() {
        return location.protocol === 'file:';
    }

    function classify(err) {
        var name = (err && (err.name || err.code)) || '';
        if (name === 'NotAllowedError' || name === 'PermissionDeniedError'
            || name === 'SecurityError' || name === 'PermissionDismissedError') {
            return 'denied';
        }
        if (name === 'NotFoundError' || name === 'DevicesNotFoundError'
            || name === 'OverconstrainedError' || name === 'ConstraintNotSatisfiedError') {
            return 'nodevice';
        }
        if (name === 'NotReadableError' || name === 'TrackStartError' || name === 'AbortError') {
            return 'busy';
        }
        return 'error';
    }

    function log10(x) {
        return Math.log(x) / Math.LN10;
    }

    function toDb(amplitude) {
        if (!(amplitude > 0)) { return MIN_DBFS; }
        var db = 20 * log10(amplitude);
        return db < MIN_DBFS ? MIN_DBFS : db;
    }

    function raf(fn) {
        if (typeof global.requestAnimationFrame === 'function') {
            return global.requestAnimationFrame(fn);
        }
        return global.setTimeout(fn, 60);
    }

    function cancelRaf(id) {
        if (typeof global.cancelAnimationFrame === 'function') {
            global.cancelAnimationFrame(id);
            return;
        }
        global.clearTimeout(id);
    }

    function now() {
        return typeof global.performance !== 'undefined' && global.performance.now
            ? global.performance.now()
            : Date.now();
    }

    function create() {
        var ctx = null;
        var stream = null;
        var source = null;
        var analyser = null;
        var floatBuf = null;
        var byteBuf = null;
        var useFloat = false;
        var rafId = 0;
        var state = 'closed';
        var smooth = 0.8;
        var offset = 90;
        var fftSize = DEFAULT_FFT;
        var smoothed = null;
        var handlers = {};

        function emitState(next, detail) {
            state = next;
            if (typeof handlers.onState === 'function') {
                try { handlers.onState(next, detail || null); } catch (err) { /* 工具侧异常不阻断采集 */ }
            }
        }

        function cleanup() {
            if (rafId) {
                cancelRaf(rafId);
                rafId = 0;
            }
            if (source && typeof source.disconnect === 'function') {
                try { source.disconnect(); } catch (err) { /* 忽略 */ }
            }
            if (stream) {
                var tracks = typeof stream.getTracks === 'function' ? stream.getTracks() : [];
                for (var i = 0; i < tracks.length; i++) {
                    try { tracks[i].stop(); } catch (err2) { /* 忽略 */ }
                }
            }
            if (ctx && typeof ctx.close === 'function') {
                try { ctx.close(); } catch (err3) { /* 忽略 */ }
            }
            ctx = stream = source = analyser = floatBuf = byteBuf = null;
            smoothed = null;
        }

        function sample() {
            if (!analyser) { rafId = 0; return; }

            var sum = 0;
            var peak = 0;
            var i;
            var v;

            if (useFloat) {
                analyser.getFloatTimeDomainData(floatBuf);
                for (i = 0; i < floatBuf.length; i++) {
                    v = floatBuf[i];
                    sum += v * v;
                    if (v > peak) { peak = v; } else if (-v > peak) { peak = -v; }
                }
                sum = sum / floatBuf.length;
            } else {
                analyser.getByteTimeDomainData(byteBuf);
                for (i = 0; i < byteBuf.length; i++) {
                    v = (byteBuf[i] - 128) / 128;
                    sum += v * v;
                    if (v > peak) { peak = v; } else if (-v > peak) { peak = -v; }
                }
                sum = sum / byteBuf.length;
            }

            var rms = Math.sqrt(sum);
            var dbfs = toDb(rms);
            var db = dbfs + offset;
            smoothed = smoothed === null ? db : smoothed * smooth + db * (1 - smooth);

            if (typeof handlers.onFrame === 'function') {
                try {
                    handlers.onFrame({
                        rms: rms,
                        dbfs: dbfs,
                        db: db,
                        smoothed: smoothed,
                        peak: toDb(peak) + offset,
                        ts: now()
                    });
                } catch (err) { /* 工具侧异常不阻断采集 */ }
            }

            rafId = raf(sample);
        }

        function start(s) {
            try {
                var Ctor = global.AudioContext || global.webkitAudioContext;
                ctx = new Ctor();
                stream = s;
                source = ctx.createMediaStreamSource(s);
                analyser = ctx.createAnalyser();
                analyser.fftSize = fftSize;
                /* 自己做 EMA，关掉 analysed 内部平滑，读数更可控 */
                if (typeof analyser.smoothingTimeConstant === 'number') {
                    analyser.smoothingTimeConstant = 0;
                }
                source.connect(analyser);

                useFloat = typeof analyser.getFloatTimeDomainData === 'function';
                if (useFloat) {
                    floatBuf = new Float32Array(analyser.fftSize);
                } else {
                    byteBuf = new Uint8Array(analyser.fftSize);
                }

                if (ctx.state === 'suspended' && typeof ctx.resume === 'function') {
                    try { ctx.resume(); } catch (err) { /* 忽略 */ }
                }

                emitState('running');
                rafId = raf(sample);
            } catch (err) {
                cleanup();
                emitState('error', err);
            }
        }

        function open(options) {
            var opt = options || {};
            handlers.onState = opt.onState;
            handlers.onFrame = opt.onFrame;

            if (typeof opt.smooth === 'number') {
                smooth = Math.max(0, Math.min(0.98, opt.smooth));
            }
            if (typeof opt.offset === 'number') {
                offset = opt.offset;
            }
            if (typeof opt.fftSize === 'number' && opt.fftSize >= 32) {
                /* fftSize 必须是 2 的幂且 ≤ 32768，否则 createAnalyser 会抛异常 */
                fftSize = Math.min(32768, Math.pow(2, Math.round(Math.log(opt.fftSize) / Math.LN2)));
            }

            if (state === 'running' || state === 'pending') { close(); }

            if (!supported()) { emitState('unsupported'); return; }
            if (!isSecure()) { emitState('insecure'); return; }
            if (!hasAudioContext()) { emitState('unsupported', 'AudioContext'); return; }

            emitState('pending');

            function ok(s) { start(s); }
            function fail(err) { cleanup(); emitState(classify(err), err); }

            /* 关掉自动增益 / 降噪 / 回声消除：否则测到的是被处理过的声音 */
            var constraints = {
                audio: {
                    echoCancellation: false,
                    noiseSuppression: false,
                    autoGainControl: false,
                    channelCount: 1
                },
                video: false
            };

            var md = getMediaDevices();
            if (md) {
                var promise = md.getUserMedia(constraints);
                if (promise && typeof promise.then === 'function') {
                    promise.then(ok, fail);
                } else {
                    fail(new Error('getUserMedia 未返回 Promise'));
                }
                return;
            }

            var legacy = legacyGetUserMedia();
            if (!legacy) { emitState('unsupported'); return; }
            try {
                legacy.call(navigator, { audio: true }, ok, fail);
            } catch (err) {
                fail(err);
            }
        }

        function close() {
            if (state === 'closed') { return; }
            cleanup();
            emitState('closed');
        }

        return {
            open: open,
            close: close,
            isOpen: function () { return state === 'running'; },
            state: function () { return state; },
            /** 校准偏移（dBFS → 近似 dB SPL），工具可让用户按参照仪校准 */
            setOffset: function (value) {
                if (typeof value === 'number') { offset = value; }
            },
            getOffset: function () { return offset; },
            setSmooth: function (value) {
                if (typeof value === 'number') { smooth = Math.max(0, Math.min(0.98, value)); }
            }
        };
    }

    ET.Mic = {
        create: create,
        supported: supported,
        isSecure: isSecure,
        isLocalFile: isLocalFile,
        hasAudioContext: hasAudioContext,
        HINTS: HINTS,
        /** 状态 → {title, tip}，未知状态回落到 error */
        hint: function (state) {
            return HINTS[state] || HINTS.error;
        }
    };

    global.ET = ET;
})(window);
