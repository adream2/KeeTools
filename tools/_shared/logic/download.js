/*!
 * download.js — 通用文件导出（Blob + a[download]）
 *
 * 共享逻辑源。改这里，然后跑：python scripts/sync_shared.py
 * 依赖：无。
 * 全局：ET.download / ET.csvCell
 *
 * 用法：
 *   ET.download('词表.csv', lines.join('\r\n'), 'text/csv');  // 默认加 UTF-8 BOM
 *   ET.download('名单.txt', text, 'text/plain');
 *   ET.download('数据.json', json, 'application/json', false); // 第 4 参 false = 不加 BOM
 *   ET.csvCell(v)  // CSV 单元格转义（含逗号/引号/换行时加引号）
 *
 * 说明：BOM 让 Excel / WPS / 记事本正确识别 UTF-8；JSON 等程序消费格式传 false。
 */
(function (global) {
    'use strict';

    var ET = global.ET = global.ET || {};

    function download(filename, text, mime, bom) {
        var type = (mime || 'text/plain') + ';charset=utf-8';
        var blob = new Blob([bom === false ? text : '\uFEFF' + text], { type: type });
        var url = URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        setTimeout(function () {
            if (a.parentNode) { a.parentNode.removeChild(a); }
            URL.revokeObjectURL(url);
        }, 120);
    }

    function csvCell(v) {
        var s = String(v === undefined || v === null ? '' : v);
        if (/[",\n\r]/.test(s)) { return '"' + s.replace(/"/g, '""') + '"'; }
        return s;
    }

    ET.download = download;
    ET.csvCell = csvCell;
})(window);
