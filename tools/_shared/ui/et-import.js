/*!
 * et-import.js — 通用数据导入组件（shell 型工具的核心基建）
 *
 * 共享逻辑源。改这里，然后跑：python scripts/sync_shared.py
 * 依赖：et-util.js（ET.store）、et-chrome.js（ET.Chrome.modal，弹窗宿主）
 * 兼容性：现代浏览器（TextDecoder GBK / FileReader / Drag&Drop）。
 * 全局：ET.Import
 *
 * 用法（名单模式，默认）：
 *   ET.Import.open({
 *     toolId: 'random-name-classic',
 *     title: '导入名单',
 *     columns: ['name', 'id', 'weight'],   // 允许识别的列
 *     current: '张三\n李四',                // 可选，回填当前数据便于增改
 *     onConfirm: function (rows, meta) { … }  // rows: [{name, id, weight}]
 *   });
 *
 * 用法（自定义字段模式，词表 / 题表等非名单数据）：
 *   ET.Import.open({
 *     toolId: 'word-flashcard',
 *     title: '导入单词表',
 *     fields: [
 *       { key: 'word', label: '单词', keys: ['单词', '英文', 'word'] },
 *       { key: 'ipa',  label: '音标', keys: ['音标', '发音', 'phonetic'] },
 *       { key: 'mean', label: '释义', keys: ['释义', '意思', 'meaning'] }
 *     ],
 *     placeholder: '…',   // 可选，覆盖粘贴框提示
 *     help: '…',          // 可选，覆盖说明文字
 *     onConfirm: function (rows, meta) { … }  // rows: [{word, ipa, mean}]
 *   });
 *   未传 fields 时行为与旧版完全一致（name / id / weight 三列）。
 *
 * 也提供纯解析入口（不弹窗）：
 *   var result = ET.Import.parseText(text, { weight: true });
 *   // result = { rows: [...], stats: { lines, valid, dupes, blanks, errors: [...] } }
 */
(function (global) {
    'use strict';

    /* ============ 常量 ============ */

    var NAME_KEYS = ['姓名', '名字', '名称', '学生', '学员', 'name', 'student'];
    var ID_KEYS = ['学号', '编号', '序号', '号码', 'id', 'no', 'number'];
    var WEIGHT_KEYS = ['权重', '次数', '倍数', 'weight', 'times'];

    var MAX_WEIGHT = 99;

    /* ============ 解析 ============ */

    function normCell(s) {
        return String(s === undefined || s === null ? '' : s).trim();
    }

    function matchKey(cell, keys) {
        var c = normCell(cell).toLowerCase();
        if (!c) { return false; }
        for (var i = 0; i < keys.length; i++) {
            if (c === keys[i]) { return true; }
        }
        for (var j = 0; j < keys.length; j++) {
            /* 中文表头常见"学生姓名"，用包含匹配 */
            if (/[\u4e00-\u9fa5]/.test(keys[j]) && c.indexOf(keys[j]) !== -1) { return true; }
        }
        return false;
    }

    function isAllNumeric(arr) {
        if (!arr.length) { return false; }
        for (var i = 0; i < arr.length; i++) {
            if (!/^\d{1,6}(\.\d+)?$/.test(normCell(arr[i]))) { return false; }
        }
        return true;
    }

    /** 引号感知的 CSV 行拆分（RFC4180 简化版，不支持跨行引号） */
    function splitLine(line, delim) {
        var out = [];
        var cur = '';
        var inQ = false;
        for (var i = 0; i < line.length; i++) {
            var ch = line[i];
            if (inQ) {
                if (ch === '"') {
                    if (line.charAt(i + 1) === '"') { cur += '"'; i++; }
                    else { inQ = false; }
                } else {
                    cur += ch;
                }
            } else if (ch === '"') {
                inQ = true;
            } else if (ch === delim) {
                out.push(cur);
                cur = '';
            } else {
                cur += ch;
            }
        }
        out.push(cur);
        return out;
    }

    function detectDelimiter(lines) {
        var counts = { '\t': 0, ',': 0, ';': 0 };
        for (var i = 0; i < lines.length; i++) {
            for (var d in counts) {
                counts[d] += lines[i].split(d).length - 1;
            }
        }
        var best = '\t';
        var bestN = -1;
        for (var k in counts) {
            if (counts[k] > bestN) { bestN = counts[k]; best = k; }
        }
        return bestN > 0 ? best : null;
    }

    /**
     * 把"原始表格行（二维数组）"规范化为 rows。
     * opts.weight 是否识别权重列；opts.id 是否识别学号列。
     */
    function tableToRows(table, opts, stats) {
        opts = opts || {};
        if (!table.length) { return []; }

        var width = 0;
        for (var w = 0; w < table.length; w++) {
            width = Math.max(width, table[w].length);
        }

        /* --- 表头识别 --- */
        var header = null;
        var first = table[0];
        var firstAllEmpty = true;
        for (var c0 = 0; c0 < first.length; c0++) {
            if (normCell(first[c0])) { firstAllEmpty = false; break; }
        }
        if (!firstAllEmpty) {
            for (var c = 0; c < first.length; c++) {
                if (matchKey(first[c], NAME_KEYS) || matchKey(first[c], WEIGHT_KEYS) || matchKey(first[c], ID_KEYS)) {
                    header = first;
                    table = table.slice(1);
                    stats.blanks++; /* 表头行不算数据 */
                    break;
                }
            }
        }

        /* --- 列映射 --- */
        var map = { name: -1, id: -1, weight: -1 };
        if (header) {
            for (var hc = 0; hc < header.length; hc++) {
                if (map.name < 0 && matchKey(header[hc], NAME_KEYS)) { map.name = hc; }
                else if (opts.id && map.id < 0 && matchKey(header[hc], ID_KEYS)) { map.id = hc; }
                else if (opts.weight && map.weight < 0 && matchKey(header[hc], WEIGHT_KEYS)) { map.weight = hc; }
            }
        }

        var cols = table.map(function (row) { return row; });
        function colValues(idx) {
            return cols.map(function (r) { return normCell(r[idx]); }).filter(function (v) { return v !== ''; });
        }

        if (map.name < 0) {
            if (width === 1) {
                map.name = 0;
            } else {
                var c0 = colValues(0);
                var c1 = colValues(1);
                var numeric0 = isAllNumeric(c0);
                var numeric1 = isAllNumeric(c1);
                if (width === 2) {
                    if (numeric0 && !numeric1) { map.id = 0; map.name = 1; }
                    else if (numeric1 && !numeric0 && opts.weight) { map.name = 0; map.weight = 1; }
                    else { map.name = 0; }
                } else {
                    /* ≥3 列：编号 | 姓名 | 权重 的经典布局 */
                    if (numeric0) { map.id = 0; map.name = 1; } else { map.name = 0; }
                    if (opts.weight) { map.weight = width - 1; }
                }
            }
        }
        if (map.name < 0) { map.name = 0; }
        if (!opts.weight) { map.weight = -1; }
        if (!opts.id) { map.id = -1; }

        /* --- 逐行处理 --- */
        var rows = [];
        var seen = {};
        var wantWeight = map.weight >= 0;

        for (var i = 0; i < table.length; i++) {
            var raw = table[i];
            var lineNo = i + (header ? 2 : 1); /* 用户视角的行号（含表头） */
            var name = map.name < raw.length ? normCell(raw[map.name]) : '';
            var restEmpty = true;
            for (var rc = 0; rc < raw.length; rc++) {
                if (normCell(raw[rc])) { restEmpty = false; break; }
            }
            if (!name && restEmpty) { stats.blanks++; continue; }

            if (!name) {
                stats.errors.push('第 ' + lineNo + ' 行：姓名列为空，已跳过');
                continue;
            }

            var weight = 1;
            if (wantWeight && map.weight < raw.length) {
                var wv = normCell(raw[map.weight]);
                if (wv !== '') {
                    var wn = Number(wv);
                    if (isFinite(wn) && wn >= 1) {
                        weight = Math.min(MAX_WEIGHT, Math.round(wn));
                        if (weight !== wn) {
                            stats.errors.push('第 ' + lineNo + ' 行：权重 "' + wv + '" 已取整为 ' + weight);
                        }
                    } else {
                        stats.errors.push('第 ' + lineNo + ' 行：权重 "' + wv + '" 无效（需 ≥1 的数字），按 1 处理');
                    }
                }
            }

            var id = '';
            if (map.id >= 0 && map.id < raw.length) {
                id = normCell(raw[map.id]);
            }

            if (opts.dedupe !== false && seen[name]) {
                stats.dupes++;
                continue;
            }
            seen[name] = true;

            rows.push({ name: name, id: id, weight: weight });
        }
        return rows;
    }

    /* ============ 自定义字段模式（词表 / 题表等非名单数据） ============ */

    function normKeyList(field) {
        var keys = field.keys && field.keys.length ? field.keys.slice() : [];
        if (field.label) { keys.push(field.label); }
        return keys;
    }

    function matchAnyKey(cell, keys) {
        for (var i = 0; i < keys.length; i++) {
            if (matchKey(cell, [keys[i]])) { return true; }
        }
        return false;
    }

    function colTaken(map, idx) {
        for (var k in map) {
            if (map[k] === idx) { return true; }
        }
        return false;
    }

    /**
     * 把二维表格规范化为「按 fields 定义的对象数组」。
     * fields = [{key, label, keys?}]，行对象形如 {key1: '…', key2: '…'}。
     * 与 tableToRows 并列存在：只有显式传 fields 时才走这里，名单路径行为不变。
     */
    function tableToFields(table, fields, stats) {
        if (!table.length) { return []; }

        var width = 0;
        for (var w = 0; w < table.length; w++) {
            width = Math.max(width, table[w].length);
        }

        var keyLists = [];
        var flat = [];
        for (var f = 0; f < fields.length; f++) {
            var list = normKeyList(fields[f]);
            keyLists.push(list);
            for (var k = 0; k < list.length; k++) { flat.push(list[k]); }
        }

        /* --- 表头识别 --- */
        var header = null;
        var first = table[0];
        var hasAny = false;
        for (var c0 = 0; c0 < first.length; c0++) {
            if (normCell(first[c0])) { hasAny = true; break; }
        }
        if (hasAny) {
            for (var c = 0; c < first.length; c++) {
                if (matchAnyKey(first[c], flat)) {
                    header = first;
                    table = table.slice(1);
                    stats.blanks++; /* 表头行不算数据 */
                    break;
                }
            }
        }

        /* --- 列映射：先按表头，缺的按出现顺序补空列 --- */
        var map = {};
        for (var fi = 0; fi < fields.length; fi++) { map[fields[fi].key] = -1; }

        if (header) {
            for (var hc = 0; hc < header.length; hc++) {
                for (var fj = 0; fj < fields.length; fj++) {
                    if (map[fields[fj].key] < 0 && matchAnyKey(header[hc], keyLists[fj])) {
                        map[fields[fj].key] = hc;
                        break;
                    }
                }
            }
        }

        var next = 0;
        for (var fp = 0; fp < fields.length; fp++) {
            if (map[fields[fp].key] >= 0) { continue; }
            while (next < width && colTaken(map, next)) { next++; }
            if (next < width) {
                map[fields[fp].key] = next;
                next++;
            }
        }

        /* --- 逐行处理 --- */
        var rows = [];
        var seen = {};
        var firstKey = fields[0].key;

        for (var i = 0; i < table.length; i++) {
            var raw = table[i];
            var lineNo = i + (header ? 2 : 1);
            var row = {};
            var allEmpty = true;

            for (var rf = 0; rf < fields.length; rf++) {
                var col = map[fields[rf].key];
                var val = (col >= 0 && col < raw.length) ? normCell(raw[col]) : '';
                row[fields[rf].key] = val;
                if (val) { allEmpty = false; }
            }

            if (allEmpty) { stats.blanks++; continue; }

            if (!row[firstKey]) {
                stats.errors.push('第 ' + lineNo + ' 行：' + (fields[0].label || '首列') + '为空，已跳过');
                continue;
            }

            if (seen[row[firstKey]]) { stats.dupes++; continue; }
            seen[row[firstKey]] = true;

            rows.push(row);
        }
        return rows;
    }

    /** 统一入口：有 fields 走自定义字段，否则沿用名单路径 */
    function buildRows(table, opts, stats) {
        return opts.fields ? tableToFields(table, opts.fields, stats) : tableToRows(table, opts, stats);
    }

    function jsonToTableFields(json, fields) {
        if (!Array.isArray(json)) { return []; }
        var labels = [];
        for (var i = 0; i < fields.length; i++) { labels.push(fields[i].label || fields[i].key); }
        var table = [labels];

        for (var r = 0; r < json.length; r++) {
            var item = json[r];
            var out = [];
            if (typeof item === 'string' || typeof item === 'number') {
                for (var c = 0; c < fields.length; c++) {
                    out.push(c === 0 ? String(item) : '');
                }
            } else if (item && typeof item === 'object') {
                for (var c2 = 0; c2 < fields.length; c2++) {
                    var v = item[fields[c2].key];
                    if (v === undefined || v === null) { v = item[fields[c2].label] || ''; }
                    out.push(String(v));
                }
            } else {
                continue;
            }
            table.push(out);
        }
        return table;
    }

    function jsonToTable(json) {
        if (Array.isArray(json)) {
            var table = [];
            for (var i = 0; i < json.length; i++) {
                var item = json[i];
                if (typeof item === 'string' || typeof item === 'number') {
                    table.push([String(item)]);
                } else if (item && typeof item === 'object') {
                    var name = item.name || item.姓名 || item.名字 || item.title || '';
                    var weight = item.weight || item.权重 || item.次数 || '';
                    var id = item.id || item.学号 || item.编号 || '';
                    if (name !== undefined && name !== null) {
                        table.push([String(id), String(name), String(weight)]);
                    }
                }
            }
            return table;
        }
        return [];
    }

    /**
     * 纯文本解析入口。
     * 返回 { rows, stats }；stats = { lines, valid, dupes, blanks, errors[] }
     */
    function parseText(text, opts) {
        opts = opts || {};
        var stats = { lines: 0, valid: 0, dupes: 0, blanks: 0, errors: [] };
        var rows = [];

        var trimmed = String(text || '').replace(/^\uFEFF/, '').trim();
        if (!trimmed) {
            return { rows: rows, stats: stats };
        }

        /* JSON 优先 */
        if (trimmed.charAt(0) === '[' || trimmed.charAt(0) === '{') {
            try {
                var json = JSON.parse(trimmed);
                var jsonTable = opts.fields ? jsonToTableFields(json, opts.fields) : jsonToTable(json);
                rows = buildRows(jsonTable, opts, stats);
            } catch (err) {
                stats.errors.push('JSON 解析失败：' + err.message);
            }
        } else {
            var lines = trimmed.split(/\r\n|\r|\n/);
            while (lines.length && !lines[lines.length - 1].trim()) { lines.pop(); }
            stats.lines = lines.length;

            var delim = detectDelimiter(lines);
            var table = [];
            for (var i = 0; i < lines.length; i++) {
                if (!lines[i].trim()) { continue; }
                table.push(delim ? splitLine(lines[i], delim) : [lines[i]]);
            }
            rows = buildRows(table, opts, stats);
        }

        stats.valid = rows.length;
        return { rows: rows, stats: stats };
    }

    /* ============ 文件解码 ============ */

    function decodeBuffer(buffer) {
        var bytes = new Uint8Array(buffer);
        /* UTF-8 BOM */
        if (bytes[0] === 0xEF && bytes[1] === 0xBB && bytes[2] === 0xBF) {
            return new TextDecoder('utf-8').decode(bytes.subarray(3));
        }
        /* UTF-16 LE BOM */
        if (bytes[0] === 0xFF && bytes[1] === 0xFE) {
            return new TextDecoder('utf-16le').decode(bytes.subarray(2));
        }
        /* 严格 UTF-8 失败 → GBK / GB18030（Excel 导出的中文 CSV 常见） */
        try {
            return new TextDecoder('utf-8', { fatal: true }).decode(bytes);
        } catch (err) {
            try { return new TextDecoder('gb18030').decode(bytes); }
            catch (err2) { return new TextDecoder('gbk').decode(bytes); }
        }
    }

    /* ============ 弹窗 UI ============ */

    function esc(s) {
        return String(s === undefined || s === null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    function chip(label, n, tone) {
        if (!n) { return ''; }
        return '<span class="etimp-chip' + (tone ? ' etimp-chip--' + tone : '') + '">' + esc(label) + ' ' + n + '</span>';
    }

    function renderResult(container, result, opts) {
        var rows = result.rows;
        var stats = result.stats;
        var html = '';

        if (!stats.lines && !rows.length && !stats.errors.length) {
            container.innerHTML = '<div class="etimp-empty">输入内容后这里会显示解析结果</div>';
            return;
        }

        html += '<div class="etimp-stats">';
        html += chip('有效', rows.length, rows.length ? 'ok' : '');
        if (stats.dupes) { html += chip('去重', stats.dupes, 'warn'); }
        if (stats.blanks) { html += chip('空行/表头', stats.blanks, ''); }
        html += '</div>';

        if (rows.length) {
            html += '<div class="etimp-table-wrap"><table class="etimp-table"><thead><tr>';
            html += '<th>#</th>';
            if (opts.fields) {
                for (var lc = 0; lc < opts.fields.length; lc++) {
                    html += '<th>' + esc(opts.fields[lc].label || opts.fields[lc].key) + '</th>';
                }
            } else {
                html += '<th>姓名</th>';
                if (opts.id) { html += '<th>学号</th>'; }
                if (opts.weight) { html += '<th>权重</th>'; }
            }
            html += '</tr></thead><tbody>';
            var previewMax = Math.min(rows.length, 10);
            for (var i = 0; i < previewMax; i++) {
                html += '<tr><td class="etimp-dim">' + (i + 1) + '</td>';
                if (opts.fields) {
                    for (var fc = 0; fc < opts.fields.length; fc++) {
                        html += '<td>' + esc(rows[i][opts.fields[fc].key] || '—') + '</td>';
                    }
                } else {
                    html += '<td>' + esc(rows[i].name) + '</td>';
                    if (opts.id) { html += '<td class="etimp-dim">' + esc(rows[i].id || '—') + '</td>'; }
                    if (opts.weight) { html += '<td class="etimp-num">' + rows[i].weight + '</td>'; }
                }
                html += '</tr>';
            }
            html += '</tbody></table></div>';
            if (rows.length > previewMax) {
                html += '<div class="etimp-empty" style="margin-top:8px;border:none;padding:6px">… 共 ' + rows.length + ' 条，仅预览前 ' + previewMax + ' 条</div>';
            }
        } else if (!stats.errors.length) {
            html += '<div class="etimp-empty">未解析出有效数据</div>';
        }

        if (stats.errors.length) {
            html += '<div class="etimp-errors"><b>' + stats.errors.length + ' 处问题：</b><br>' +
                stats.errors.slice(0, 20).map(esc).join('<br>') +
                (stats.errors.length > 20 ? '<br>… 还有 ' + (stats.errors.length - 20) + ' 条' : '') + '</div>';
        }

        container.innerHTML = html;
    }

    function open(options) {
        var opt = options || {};
        var toolId = opt.toolId || 'unknown-tool';
        var customFields = (opt.fields && opt.fields.length) ? opt.fields : null;
        var opts = {
            weight: customFields ? false : (opt.columns || ['name', 'weight']).indexOf('weight') !== -1,
            id: customFields ? false : (opt.columns || []).indexOf('id') !== -1,
            fields: customFields,
            dedupe: true
        };

        var Chrome = global.ET && global.ET.Chrome;
        if (!Chrome || !Chrome.modal) {
            if (opt.onConfirm) { opt.onConfirm([], { cancelled: true }); }
            return null;
        }

        var defaultPlaceholder = customFields
            ? ('一行一条，按列依次填写，例如：\n' + customFields.map(function (f) {
                return '示例' + (f.label || f.key);
            }).join(', '))
            : '一行一个人，支持粘贴 Excel 表格、逗号/Tab 分隔文本，例如：\n张三\n李四,2\n1,王五,3';

        var defaultHelp = customFields
            ? ('每行一条，按列依次为 <b>' + customFields.map(function (f) {
                return esc(f.label || f.key);
            }).join(' / ') + '</b>；支持逗号 / Tab 分隔文本、带表头的 CSV 与 JSON 数组。首列重复的行会自动去重。')
            : ('支持 <b>纯姓名</b>（一行一个）、<b>姓名,权重</b>、<b>学号,姓名,权重</b>，' +
                '也支持带表头的 CSV（姓名 / 学号 / 权重 列自动识别）与 JSON 数组。' +
                (opts.weight ? '权重越大被抽中概率越高，默认 1。' : ''));

        var root = document.createElement('div');
        root.innerHTML =
            '<div class="etimp-tabs">' +
            '<button type="button" class="etimp-tab is-active" data-tab="paste"><b>粘贴文本</b></button>' +
            '<button type="button" class="etimp-tab" data-tab="file">打开文件</button>' +
            '</div>' +
            '<div data-panel="paste">' +
            '<textarea class="etimp-textarea" id="etimp-text" placeholder="' +
            esc(opt.placeholder || defaultPlaceholder) + '"></textarea>' +
            '<div class="etimp-help">' + (opt.help || defaultHelp) + '</div>' +
            '</div>' +
            '<div data-panel="file" hidden>' +
            '<div class="etimp-drop" id="etimp-drop" tabindex="0">' +
            '<span class="et-drop-icon"></span>' +
            '<span class="etimp-drop__main">点击选择或拖入文件</span>' +
            '<span class="etimp-drop__hint">支持 .csv / .txt / .json，自动识别 UTF-8 与 GBK 编码</span>' +
            '<input type="file" class="etimp-file" id="etimp-file" accept=".csv,.txt,.tsv,.json,text/csv,text/plain,application/json">' +
            '</div>' +
            '<div class="etimp-help">Excel 请先另存为 <b>CSV</b> 再导入（文件 → 另存为 → CSV）。</div>' +
            '</div>' +
            '<div class="etimp-result" id="etimp-result"><div class="etimp-empty">输入内容后这里会显示解析结果</div></div>';

        var body = root.firstChild ? root : root;
        /* root 本身作为容器 */
        var wrap = document.createElement('div');
        while (root.firstChild) { wrap.appendChild(root.firstChild); }

        var modal = global.ET.Chrome.modal({
            title: opt.title || '导入数据',
            icon: 'i-upload',
            size: 'wide',
            body: wrap,
            buttons: [
                { label: '取消' },
                {
                    label: '导入',
                    primary: true,
                    onClick: function (close) {
                        if (!lastResult || !lastResult.rows.length) { return; }
                        close();
                        if (opt.onConfirm) {
                            opt.onConfirm(lastResult.rows, {
                                dupes: lastResult.stats.dupes,
                                dedupe: opts.dedupe
                            });
                        }
                    }
                }
            ]
        });

        var q = function (sel) { return wrap.querySelector(sel); };
        var textEl = q('#etimp-text');
        var resultEl = q('#etimp-result');
        var importBtn = modal.foot.querySelector('.et-btn--primary');
        var lastResult = null;

        if (opt.current) { textEl.value = opt.current; }

        function refresh() {
            var text = textEl.value;
            lastResult = parseText(text, opts);
            renderResult(resultEl, lastResult, opts);
            importBtn.disabled = !lastResult.rows.length;
            if (lastResult.rows.length) {
                importBtn.textContent = '导入 ' + lastResult.rows.length + ' 条';
            } else {
                importBtn.textContent = '导入';
            }
        }

        var timer = 0;
        textEl.addEventListener('input', function () {
            clearTimeout(timer);
            timer = setTimeout(refresh, 250);
        });

        /* 页签切换 */
        wrap.querySelectorAll('.etimp-tab').forEach(function (tab) {
            tab.addEventListener('click', function () {
                wrap.querySelectorAll('.etimp-tab').forEach(function (t) { t.classList.remove('is-active'); });
                tab.classList.add('is-active');
                var name = tab.getAttribute('data-tab');
                q('[data-panel="paste"]').hidden = name !== 'paste';
                q('[data-panel="file"]').hidden = name !== 'file';
            });
        });

        /* 文件区 */
        var drop = q('#etimp-drop');
        var fileInput = q('#etimp-file');

        var dropIconHost = drop.querySelector('.et-drop-icon');
        var NS_SVG = 'http://www.w3.org/2000/svg'; /* et-allow-external SVG 命名空间 */
        var dropSvg = document.createElementNS(NS_SVG, 'svg');
        dropSvg.setAttribute('class', 'et-icon');
        var dropUse = document.createElementNS(NS_SVG, 'use');
        dropUse.setAttribute('href', '#i-upload');
        dropSvg.appendChild(dropUse);
        dropIconHost.appendChild(dropSvg);

        drop.addEventListener('click', function () { fileInput.click(); });
        drop.addEventListener('keydown', function (ev) {
            if (ev.key === 'Enter' || ev.key === ' ') { ev.preventDefault(); fileInput.click(); }
        });
        ['dragenter', 'dragover'].forEach(function (evName) {
            drop.addEventListener(evName, function (ev) {
                ev.preventDefault();
                drop.classList.add('is-over');
            });
        });
        ['dragleave', 'drop'].forEach(function (evName) {
            drop.addEventListener(evName, function (ev) {
                ev.preventDefault();
                drop.classList.remove('is-over');
            });
        });
        drop.addEventListener('drop', function (ev) {
            var files = ev.dataTransfer && ev.dataTransfer.files;
            if (files && files.length) { readFile(files[0]); }
        });
        fileInput.addEventListener('change', function () {
            if (fileInput.files && fileInput.files.length) { readFile(fileInput.files[0]); }
        });

        function readFile(file) {
            var reader = new FileReader();
            reader.onload = function () {
                var text = decodeBuffer(reader.result);
                var isJson = /\.json$/i.test(file.name) ||
                    (text.charAt(0) !== '\n' && (text.charAt(0) === '['));
                if (isJson) {
                    try {
                        JSON.parse(text.trim()); /* 校验合法性，错误交给 parseText 报 */
                    } catch (err) { /* parseText 会再次报错 */ }
                }
                textEl.value = text;
                /* 切回粘贴页让用户可见内容 */
                wrap.querySelectorAll('.etimp-tab').forEach(function (t) {
                    t.classList.toggle('is-active', t.getAttribute('data-tab') === 'paste');
                });
                q('[data-panel="paste"]').hidden = false;
                q('[data-panel="file"]').hidden = true;
                refresh();
                global.ET.Chrome.toast('已读取 ' + file.name, 'ok');
            };
            reader.onerror = function () {
                global.ET.Chrome.toast('文件读取失败', 'err');
            };
            reader.readAsArrayBuffer(file);
        }

        refresh();
        setTimeout(function () { textEl.focus(); }, 60);

        return modal;
    }

    global.ET = global.ET || {};
    global.ET.Import = { open: open, parseText: parseText, decodeBuffer: decodeBuffer };
})(window);
