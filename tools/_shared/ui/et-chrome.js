/*!
 * et-chrome.js — 工具通用外壳（顶栏 / 页脚 / 主题 / 全屏 / 音效 / 设置抽屉 / 帮助 / PWA）
 *
 * 共享逻辑源。改这里，然后跑：python scripts/sync_shared.py
 * 依赖：et-util.js（ET.store）
 * 兼容性：现代浏览器优先（Chrome 90+），无外部依赖。
 * 全局：ET.Chrome
 *
 * 工具 HTML 约定的骨架：
 *   <body class="et-app">
 *     <header id="et-topbar"></header>
 *     <main id="et-main">…工具内容…</main>
 *     <footer id="et-footer"></footer>
 *   </body>
 *
 * 用法：
 *   var chrome = ET.Chrome.mount({
 *     toolId: 'random-name-classic',
 *     title: '随机点名器',
 *     sub: '导入名单 · 课堂互动',
 *     accent: '#e11d48',          // 可选，主题色
 *     status: '就绪',              // 可选，初始状态
 *     sound: true,                 // 可选，显示音效开关
 *     help: '<div>…帮助内容…</div>', // 可选
 *     settings: {                  // 可选，右上角「设置」抽屉（edupick 交互）
 *       title: '名单 / 设置',
 *       build: function (body, api) { … }  // 每次打开重建内容
 *     },
 *     actions: [{icon:'i-dice', label:'抽一个', onClick: fn}] // 可选
 *   });
 *
 * iframe 全屏委托：工具在 iframe 内运行时，全屏按钮向父窗口发送
 * {type:'et-fullscreen-toggle'}，由宿主页面统一 fullscreen（避免双层全屏）；
 * 宿主应回传 {type:'et-fullscreen-change', value:bool} 同步按钮图标。
 *
 * 站点回流（postMessage 协议）：
 *   工具加载后向父窗口发送 {type:'et-hello', toolId}；
 *   宿主页面可回复 {type:'et-origin', origin:'https://…'}，
 *   工具据此渲染页脚官网链接。也可直接提供 window.ET_SITE_ORIGIN。
 *   收不到任何来源时页脚只显示纯文本（离线可用，必须有默认值）。
 */
(function (global) {
    'use strict';

    var NS_SVG = 'http://www.w3.org/2000/svg'; /* et-allow-external SVG 命名空间 */
    var NS_XLINK = 'http://www.w3.org/1999/xlink'; /* et-allow-external SVG 命名空间 */

    /* ---------- 内部工具 ---------- */

    function makeIcon(id, cls) {
        var svg = document.createElementNS(NS_SVG, 'svg');
        svg.setAttribute('class', cls || 'et-icon');
        var use = document.createElementNS(NS_SVG, 'use');
        use.setAttribute('href', '#' + id);
        use.setAttributeNS(NS_XLINK, 'xlink:href', '#' + id);
        svg.appendChild(use);
        return svg;
    }

    function makeBtn(icon, title, onClick) {
        var b = document.createElement('button');
        b.className = 'et-icon-btn';
        b.type = 'button';
        b.title = title;
        b.setAttribute('aria-label', title);
        b.appendChild(makeIcon(icon));
        if (onClick) { b.addEventListener('click', onClick); }
        return b;
    }

    function el(tag, cls, text) {
        var node = document.createElement(tag);
        if (cls) { node.className = cls; }
        if (text !== undefined && text !== null) { node.textContent = text; }
        return node;
    }

    /* ---------- 音效（WebAudio，无音频文件，音量按教室大屏调校） ---------- */

    var audioCtx = null;

    function ensureCtx() {
        if (!audioCtx) {
            var AC = global.AudioContext || global.webkitAudioContext;
            if (!AC) { return null; }
            try { audioCtx = new AC(); } catch (err) { return null; }
        }
        if (audioCtx.state === 'suspended') {
            try { audioCtx.resume(); } catch (err) { /* 忽略 */ }
        }
        return audioCtx;
    }

    var MASTER_GAIN = 0.5; /* 整体音量层（教室设备音量 100% 也要听得清） */

    function tone(ctx, freq, start, dur, gainPeak, type) {
        var osc = ctx.createOscillator();
        var gain = ctx.createGain();
        osc.type = type || 'triangle';
        osc.frequency.value = freq;
        gain.gain.setValueAtTime(0, start);
        gain.gain.linearRampToValueAtTime(gainPeak, start + 0.012);
        gain.gain.exponentialRampToValueAtTime(0.0001, start + dur);
        osc.connect(gain);
        gain.connect(ctx.destination);
        osc.start(start);
        osc.stop(start + dur + 0.05);
    }

    var BEEPS = {
        tick: [[880, 0, 0.06, 0.42]],
        ok: [[660, 0, 0.1, 0.46], [880, 0.1, 0.14, 0.46]],
        win: [[523.25, 0, 0.16, 0.5], [659.25, 0.12, 0.16, 0.5], [783.99, 0.24, 0.16, 0.5], [1046.5, 0.36, 0.34, 0.52]],
        err: [[220, 0, 0.22, 0.46, 'square']],
        over: [[880, 0, 0.18, 0.5], [659.25, 0.2, 0.18, 0.5], [523.25, 0.4, 0.36, 0.5]]
    };

    function playBeep(kind) {
        var ctx = ensureCtx();
        if (!ctx || !BEEPS[kind]) { return; }
        var seq = BEEPS[kind];
        var t0 = ctx.currentTime + 0.01;
        for (var i = 0; i < seq.length; i++) {
            tone(ctx, seq[i][0], t0 + seq[i][1], seq[i][2], seq[i][3] * MASTER_GAIN, seq[i][4]);
        }
    }

    /* ---------- 设置抽屉 ---------- */

    function openDrawer(title, build) {
        var backdrop = el('div', 'et-drawer-backdrop');
        var drawer = el('aside', 'et-drawer');
        drawer.setAttribute('role', 'dialog');
        drawer.setAttribute('aria-label', title);

        var head = el('div', 'et-drawer__head');
        head.appendChild(makeIcon('i-sliders'));
        head.appendChild(el('span', 'et-drawer__title', title));
        head.appendChild(makeBtn('i-x', '关闭', close));
        drawer.appendChild(head);

        var body = el('div', 'et-drawer__body');
        drawer.appendChild(body);

        backdrop.appendChild(drawer);
        document.body.appendChild(backdrop);
        requestAnimationFrame(function () { backdrop.classList.add('is-open'); });

        function close() {
            backdrop.classList.remove('is-open');
            document.removeEventListener('keydown', onKey);
            setTimeout(function () {
                if (backdrop.parentNode) { backdrop.parentNode.removeChild(backdrop); }
            }, 220);
        }
        function onKey(ev) {
            if (ev.key === 'Escape') { ev.stopPropagation(); close(); }
        }
        document.addEventListener('keydown', onKey);
        backdrop.addEventListener('mousedown', function (ev) {
            if (ev.target === backdrop) { close(); }
        });

        if (typeof build === 'function') { build(body, { close: close }); }
        return { body: body, close: close };
    }

    /* ---------- 通用弹窗 / toast（同时挂模块级，供其他共享组件使用） ---------- */

    function modal(mo) {
        var backdrop = el('div', 'et-modal');
        var panel = el('div', 'et-modal__panel' + (mo.size === 'narrow' ? ' et-modal__panel--narrow'
            : mo.size === 'wide' ? ' et-modal__panel--wide' : ''));
        var head = el('div', 'et-modal__head');
        if (mo.icon) { head.appendChild(makeIcon(mo.icon)); }
        head.appendChild(el('span', null, mo.title || ''));
        head.appendChild(makeBtn('i-x', '关闭', close));
        panel.appendChild(head);

        var body = el('div', 'et-modal__body');
        if (typeof mo.body === 'string') { body.innerHTML = mo.body; }
        else if (mo.body) { body.appendChild(mo.body); }
        panel.appendChild(body);

        var foot = null;
        if (mo.buttons && mo.buttons.length) {
            foot = el('div', 'et-modal__foot');
            foot.appendChild(el('span', 'spacer'));
            mo.buttons.forEach(function (b) {
                var btn = el('button', 'et-btn' + (b.primary ? ' et-btn--primary' : '') + (b.danger ? ' et-btn--danger' : ''), b.label);
                btn.type = 'button';
                btn.addEventListener('click', function () {
                    if (b.onClick) { b.onClick(close); } else { close(); }
                });
                foot.appendChild(btn);
            });
            panel.appendChild(foot);
        }

        backdrop.appendChild(panel);
        document.body.appendChild(backdrop);

        function close() {
            if (backdrop.parentNode) { backdrop.parentNode.removeChild(backdrop); }
            document.removeEventListener('keydown', onKey);
            if (mo.onClose) { mo.onClose(); }
        }
        function onKey(ev) {
            if (ev.key === 'Escape') { ev.stopPropagation(); close(); }
        }
        document.addEventListener('keydown', onKey);
        backdrop.addEventListener('mousedown', function (ev) {
            if (ev.target === backdrop) { close(); }
        });

        return { panel: panel, body: body, foot: foot, close: close };
    }

    var toastEl = null;
    var toastTimer = 0;

    function toast(msg, tone) {
        if (!toastEl) {
            toastEl = el('div', 'et-toast');
            document.body.appendChild(toastEl);
        }
        toastEl.textContent = msg;
        toastEl.setAttribute('data-tone', tone || '');
        void toastEl.offsetWidth;
        toastEl.classList.add('is-show');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(function () { toastEl.classList.remove('is-show'); }, 2200);
    }

    /* ---------- PWA 元数据（blob manifest，环境不支持时静默跳过） ---------- */

    function installPwa(toolId, title, accent) {
        try {
            var theme = document.querySelector('meta[name="theme-color"]');
            if (!theme) {
                theme = document.createElement('meta');
                theme.setAttribute('name', 'theme-color');
                document.head.appendChild(theme);
            }
            theme.setAttribute('content', accent || '#2563eb');

            var svg = '<svg xmlns="' + NS_SVG + '" viewBox="0 0 64 64">' +
                '<rect width="64" height="64" rx="14" fill="' + (accent || '#2563eb') + '"/>' +
                '<text x="32" y="43" font-size="32" font-weight="800" font-family="sans-serif" ' +
                'fill="#fff" text-anchor="middle">' + (title || '课').charAt(0) + '</text></svg>';
            var iconUri = 'data:image/svg+xml,' + encodeURIComponent(svg);

            var mf = {
                name: title || 'KeeTools 课工具',
                short_name: title || '课工具',
                start_url: '.',
                display: 'standalone',
                background_color: '#f4f6fb',
                theme_color: accent || '#2563eb',
                icons: [{ src: iconUri, sizes: 'any', type: 'image/svg+xml', purpose: 'any' }]
            };
            var blob = new Blob([JSON.stringify(mf)], { type: 'application/manifest+json' });
            var link = document.createElement('link');
            link.rel = 'manifest';
            link.href = URL.createObjectURL(blob);
            document.head.appendChild(link);
        } catch (err) { /* 不支持 PWA 元数据的环境静默跳过 */ }
    }

    /* ---------- 挂载 ---------- */

    function mount(options) {
        var opt = options || {};
        var toolId = opt.toolId || 'unknown-tool';
        var store = global.ET && global.ET.store
            ? global.ET.store(toolId)
            : (function () { return { get: function () { return null; }, set: function () {} }; })();

        var api = { toolId: toolId, store: store };
        var soundEnabled = store.get('sound', true) !== false;

        /* 主题色 */
        if (opt.accent) {
            document.documentElement.style.setProperty('--et-accent', opt.accent);
        }

        installPwa(toolId, opt.title, opt.accent);

        /* ---------- 主题 ---------- */
        var theme = store.get('theme', null);
        if (theme !== 'dark' && theme !== 'light') {
            theme = (global.matchMedia && global.matchMedia('(prefers-color-scheme: dark)').matches)
                ? 'dark' : 'light';
        }
        applyTheme(theme);

        function applyTheme(t) {
            theme = t;
            document.documentElement.setAttribute('data-et-theme', t);
            store.set('theme', t);
            if (themeBtn) {
                themeBtn.innerHTML = '';
                themeBtn.appendChild(makeIcon(t === 'dark' ? 'i-sun' : 'i-moon'));
                themeBtn.title = t === 'dark' ? '切换到浅色' : '切换到深色';
            }
        }

        /* ---------- 顶栏 ---------- */
        var topbar = document.getElementById('et-topbar');
        var main = document.getElementById('et-main');
        var footer = document.getElementById('et-footer');

        if (!topbar) { topbar = el('header'); topbar.id = 'et-topbar'; document.body.insertBefore(topbar, document.body.firstChild); }
        if (!main) { main = el('main'); main.id = 'et-main'; topbar.after(main); }
        if (!footer) { footer = el('footer'); footer.id = 'et-footer'; document.body.appendChild(footer); }

        topbar.className = 'et-topbar';
        main.classList.add('et-main');
        footer.className = 'et-footer';

        /* 品牌 */
        var brand = el('div', 'et-brand');
        brand.appendChild(el('span', 'et-brand__dot'));
        var nameWrap = el('div');
        nameWrap.style.cssText = 'min-width:0;line-height:1.25';
        nameWrap.appendChild(el('div', 'et-brand__name', opt.title || '课工具'));
        if (opt.sub) { nameWrap.appendChild(el('div', 'et-brand__sub', opt.sub)); }
        brand.appendChild(nameWrap);
        topbar.appendChild(brand);

        /* 状态胶囊 */
        var statusPill = el('div', 'et-status');
        statusPill.hidden = true;
        statusPill.appendChild(el('span', 'et-status__dot'));
        var statusText = el('span', null, '');
        statusPill.appendChild(statusText);
        topbar.appendChild(statusPill);

        api.setStatus = function (text, tone) {
            if (!text) { statusPill.hidden = true; return; }
            statusText.textContent = text;
            statusPill.setAttribute('data-tone', tone || 'ok');
            statusPill.hidden = false;
        };
        if (opt.status) { api.setStatus(opt.status); }

        /* 动作区（edupick 交互：右上角聚合设置入口） */
        var actions = el('div', 'et-actions');

        var settingsBtn = null;
        if (opt.settings && typeof opt.settings.build === 'function') {
            settingsBtn = makeBtn('i-sliders', opt.settings.title || '设置', function () {
                openDrawer(opt.settings.title || '设置', function (body, drawerApi) {
                    opt.settings.build(body, { api: api, close: drawerApi.close });
                });
            });
            actions.appendChild(settingsBtn);
        }

        (opt.actions || []).forEach(function (a) {
            var b = makeBtn(a.icon, a.label || '', a.onClick);
            if (a.id) { b.id = a.id; }
            actions.appendChild(b);
        });

        var soundBtn = null;
        if (opt.sound) {
            soundBtn = makeBtn(soundEnabled ? 'i-volume' : 'i-volume-off',
                soundEnabled ? '关闭音效' : '开启音效', toggleSound);
            if (!soundEnabled) { soundBtn.classList.add('is-off'); }
            actions.appendChild(soundBtn);
        }

        if (opt.help) {
            actions.appendChild(makeBtn('i-help', '使用说明', function () { openHelp(); }));
        }

        var themeBtn = makeBtn('', '切换主题', function () {
            applyTheme(theme === 'dark' ? 'light' : 'dark');
        });
        applyTheme(theme); /* 填充图标 */
        actions.appendChild(themeBtn);

        /* 全屏按钮始终保留：iframe 内对本工具文档 requestFullscreen
           （宿主 iframe 已声明 allow="fullscreen"），与宿主「全屏体验」等效同屏。 */
        var fsBtn = makeBtn('i-maximize', '全屏', toggleFullscreen);
        actions.appendChild(fsBtn);

        topbar.appendChild(actions);

        function toggleSound() {
            soundEnabled = !soundEnabled;
            store.set('sound', soundEnabled);
            soundBtn.innerHTML = '';
            soundBtn.appendChild(makeIcon(soundEnabled ? 'i-volume' : 'i-volume-off'));
            soundBtn.title = soundEnabled ? '关闭音效' : '开启音效';
            soundBtn.classList.toggle('is-off', !soundEnabled);
            if (soundEnabled) { api.beep('ok'); }
        }

        api.openSettings = function () {
            if (settingsBtn) { settingsBtn.click(); }
        };

        /* ---------- 全屏（iframe 内委托宿主，避免双层全屏） ---------- */

        function inIframe() {
            try { return global.parent && global.parent !== global; } catch (err) { return true; }
        }

        function toggleFullscreen() {
            var root = document.documentElement;
            var fsEl = document.fullscreenElement || document.webkitFullscreenElement || null;
            if (fsEl) {
                (document.exitFullscreen || document.webkitExitFullscreen || function () {}).call(document);
            } else {
                var req = root.requestFullscreen || root.webkitRequestFullscreen;
                if (req) { try { req.call(root); } catch (err) { /* 忽略 */ } }
            }
        }

        function syncFsIcon(on) {
            if (!fsBtn) { return; }
            fsBtn.innerHTML = '';
            fsBtn.appendChild(makeIcon(on ? 'i-minimize' : 'i-maximize'));
            fsBtn.title = on ? '退出全屏' : '全屏';
        }

        document.addEventListener('fullscreenchange', function () {
            syncFsIcon(!!(document.fullscreenElement || document.webkitFullscreenElement));
        });
        document.addEventListener('webkitfullscreenchange', function () {
            syncFsIcon(!!(document.fullscreenElement || document.webkitFullscreenElement));
        });
        syncFsIcon(false);

        api.toggleFullscreen = toggleFullscreen;

        /* ---------- 音效 API ---------- */
        api.beep = function (kind) {
            if (!soundEnabled) { return; }
            playBeep(kind);
        };
        api.soundOn = function () { return soundEnabled; };

        api.modal = modal;

        /* ---------- 帮助 ---------- */
        function openHelp() {
            modal({
                title: '使用说明',
                icon: 'i-help',
                size: 'narrow',
                body: opt.help,
                buttons: [{ label: '知道了', primary: true }]
            });
        }
        api.openHelp = openHelp;

        /* ---------- toast ---------- */
        api.toast = toast;

        /* ---------- 页脚（L2 品牌回流，低调一行） ---------- */
        var siteOrigin = typeof global.ET_SITE_ORIGIN === 'string' && /^https?:\/\//.test(global.ET_SITE_ORIGIN)
            ? global.ET_SITE_ORIGIN
            : null;

        function renderFooter() {
            footer.textContent = '';
            footer.appendChild(el('span', null, 'KeeTools 课工具'));
            footer.appendChild(el('span', null, '·'));
            if (siteOrigin) {
                var a = el('a', null, '更多课堂工具');
                a.href = siteOrigin + '/?from=tool-footer&tool=' + encodeURIComponent(toolId);
                a.target = '_blank';
                a.rel = 'noopener';
                footer.appendChild(a);
            } else {
                footer.appendChild(el('span', null, '更多课堂工具'));
            }
        }
        renderFooter();

        /* 站点来源发现：iframe 内向父窗口询问；也可由宿主直接注入 ET_SITE_ORIGIN */
        try {
            if (global.parent && global.parent !== global) {
                global.parent.postMessage(JSON.stringify({ type: 'et-hello', toolId: toolId }), '*');
            }
        } catch (err) { /* 跨域异常忽略 */ }

        global.addEventListener('message', function (ev) {
            var data = ev.data;
            if (typeof data === 'string') {
                try { data = JSON.parse(data); } catch (err) { return; }
            }
            if (!data || typeof data !== 'object') { return; }

            if (data.type === 'et-origin' && typeof data.origin === 'string' && /^https?:\/\//.test(data.origin)) {
                siteOrigin = data.origin;
                if (!global.ET_SITE_ORIGIN) { global.ET_SITE_ORIGIN = data.origin; }
                renderFooter();
            }
            if (data.type === 'et-fullscreen-change' && typeof data.value === 'boolean') {
                syncFsIcon(data.value);
            }
        });

        api.el = { topbar: topbar, main: main, footer: footer, actions: actions };

        /* ---------- 激光笔 / 翻页笔适配（HID 翻页键） ----------
         * 翻页笔的上下页键在系统层等价于 PageDown / PageUp。
         * 工具通过 opt.primary / opt.secondary 注册动作：
         *   PageDown → primary（主操作，如开始点名 / 开始计时）
         *   PageUp   → secondary（副操作，缺省时同 primary）
         * 输入框聚焦或弹窗/抽屉打开时不响应，避免误触。 */
        if (typeof opt.primary === 'function') {
            document.addEventListener('keydown', function (ev) {
                if (ev.key !== 'PageDown' && ev.key !== 'PageUp') { return; }
                var tag = (document.activeElement && document.activeElement.tagName) || '';
                if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT') { return; }
                if (document.querySelector('.et-modal, .et-drawer-backdrop, .ws-winner')) { return; }
                ev.preventDefault();
                if (ev.key === 'PageDown') { opt.primary(); }
                else if (typeof opt.secondary === 'function') { opt.secondary(); }
                else { opt.primary(); }
            });
        }

        return api;
    }

    global.ET = global.ET || {};
    global.ET.Chrome = {
        mount: mount,
        modal: modal,
        toast: toast,
        beep: playBeep /* 挂载前的独立音效入口（不经过音效开关） */
    };
})(window);
