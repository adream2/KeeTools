<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>@@TITLE@@ — KeeTools 课工具</title>
<!-- ET-META
{"id":"@@TOOL_ID@@","version":"@@VERSION@@"}
-->

<!-- 共享外壳样式（由 sync_shared.py 注入） -->
<!-- ET:INLINE name="et-chrome-css" src="ui/et-chrome.css" type="css" -->
<style>/* 占位：运行 python scripts/sync_shared.py 填充 */</style>
<!-- /ET:INLINE -->

<style>
/* ── 工具自身样式：自包含，不引用任何外部资源 ── */
:root {
    --@@PREFIX@@-accent: @@ACCENT@@;
}

.@@PREFIX@@-main {
    flex: 1;
    min-height: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 24px;
    padding: 24px;
    text-align: center;
}

.@@PREFIX@@-stage {
    font-size: clamp(40px, 10vw, 128px);
    font-weight: 900;
    letter-spacing: -.02em;
    line-height: 1.1;
    color: var(--et-fg);
    word-break: break-word;
}

.@@PREFIX@@-hint {
    margin: 0;
    font-size: 16px;
    color: var(--et-muted);
}

.@@PREFIX@@-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    justify-content: center;
}
</style>
</head>
<body class="et-app">

<header id="et-topbar"></header>

<main id="et-main" class="@@PREFIX@@-main">
    <div class="@@PREFIX@@-stage" id="stage">@@TITLE@@</div>
    <p class="@@PREFIX@@-hint" id="hint">骨架已就绪，替换为真实交互逻辑即可。</p>

    <div class="@@PREFIX@@-actions">
        <button class="et-btn et-btn--lg et-btn--primary" id="btnRun" type="button">
            <svg class="et-icon"><use href="#i-play"></use></svg>
            <span>开始</span>
        </button>
    </div>
</main>

<footer id="et-footer"></footer>

<!-- 图标集合（由 sync_shared.py 注入） -->
<!-- ET:INLINE name="icons" src="icons/icons.svg" type="svg" -->
<svg xmlns="http://www.w3.org/2000/svg" style="display:none" aria-hidden="true"></svg>
<!-- /ET:INLINE -->

<!-- 共享逻辑（由 sync_shared.py 注入） -->
<!-- ET:INLINE name="et-util" src="logic/et-util.js" -->
<script>/* 占位：运行 python scripts/sync_shared.py 填充 */</script>
<!-- /ET:INLINE -->

<!-- ET:INLINE name="et-chrome" src="ui/et-chrome.js" -->
<script>/* 占位：运行 python scripts/sync_shared.py 填充 */</script>
<!-- /ET:INLINE -->

<!-- ET:INLINE name="et-stats" src="ui/et-stats.js" -->
<script>/* 占位：运行 python scripts/sync_shared.py 填充 */</script>
<!-- /ET:INLINE -->

<!-- 使用统计：删除上方 et-stats 块即可完全不联网（模块化设计） -->
<script>
try { ET.Stats.send('use_offline', '@@TOOL_ID@@'); } catch (e) { /* 静默 */ }
</script>

<script>
(function () {
    'use strict';

    var TOOL_ID = '@@TOOL_ID@@';
    var store = ET.store(TOOL_ID);

    var chrome = ET.Chrome.mount({
        toolId: TOOL_ID,
        title: '@@TITLE@@',
        sub: '@@SUB@@',
        accent: '@@ACCENT@@',
        status: '就绪',
        sound: true,
        primary: run,       /* 翻页笔 PageDown */
        help: '<div style="font-size:13.5px;line-height:2">' +
            '<p style="margin:0 0 8px"><b>快捷键</b>：空格 = 主操作。</p>' +
            '<p style="margin:0 0 8px"><b>翻页笔</b>：下页键 = 主操作。</p>' +
            '<p style="margin:0"><b>数据</b>：设置持久化在浏览器本地（localStorage）。</p></div>'
    });

    var els = {
        stage: document.getElementById('stage'),
        hint: document.getElementById('hint'),
        btnRun: document.getElementById('btnRun')
    };

    function isTypingTarget(node) {
        if (!node || !node.tagName) { return false; }
        var tag = node.tagName.toUpperCase();
        return tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT' || node.isContentEditable === true;
    }

    function run() {
        var count = store.get('runs', 0) + 1;
        store.set('runs', count);
        els.stage.textContent = '第 ' + count + ' 次';
        els.hint.textContent = '最后运行：' + new Date().toLocaleTimeString();
        try { chrome.beep(); } catch (e) { /* 静默 */ }
        chrome.toast('骨架运行正常');
    }

    els.btnRun.addEventListener('click', run);

    document.addEventListener('keydown', function (e) {
        if (e.code === 'Space' && !isTypingTarget(e.target)) {
            e.preventDefault();
            run();
        }
    });

    /* 恢复上次状态 */
    var runs = store.get('runs', 0);
    if (runs > 0) {
        els.hint.textContent = '本地已记录 ' + runs + ' 次运行';
    }
})();
</script>

</body>
</html>
