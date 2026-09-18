/*!
 * et-stats.js — 使用统计模块（模块化，可整体移除）
 *
 * 共享逻辑源。改这里，然后跑：python scripts/sync_shared.py
 * 依赖：无（可脱离 et-util / et-chrome 独立运行）。
 * 全局：ET.Stats
 *
 * ┌────────────────────────────────────────────────────────────────┐
 * │ 说明：本模块只做一件事——工具被打开时向上报端点发一条匿名计数。 │
 * │ · 在线（iframe 内）：宿主页面已服务端计数，本模块不重复上报。   │
 * │ · 离线打开（file:// / 下载单文件 / 离线包）：联网条件具备时，   │
 * │   以 sendBeacon 上报 use_offline 事件（text/plain 免预检）。    │
 * │ · 上报端点 = window.ET_SITE_ORIGIN（由宿主注入或离线包配置）。  │
 * │   未配置端点时本模块完全静默，不发任何请求。                    │
 * │ · **整个 <script> 块可安全删除**：删除后工具其余功能完全正常，  │
 * │   且完全不再发起任何网络请求。                                  │
 * └────────────────────────────────────────────────────────────────┘
 */
(function (global) {
    'use strict';

    var ET = global.ET = global.ET || {};

    var Stats = {
        /** 上报端点来源（站点绝对地址，无协议尾斜杠） */
        endpoint: (typeof global.ET_SITE_ORIGIN === 'string' && /^https?:\/\//.test(global.ET_SITE_ORIGIN))
            ? global.ET_SITE_ORIGIN
            : null,

        _done: {},

        /**
         * 上报一次事件（同一事件同一工具每次页面加载只报一次）。
         * event: 'use_offline' 等；toolId: 工具 id。
         */
        send: function (event, toolId) {
            if (!event || this._done[event + ':' + (toolId || '')]) { return false; }
            if (!this.endpoint) { return false; }
            /* iframe 内由宿主统计，避免双计 */
            try {
                if (global.parent && global.parent !== global) { return false; }
            } catch (err) { return false; }
            /* 断网时挂起，网络恢复自动补报一次 */
            if (global.navigator && global.navigator.onLine === false) {
                var self = this;
                global.addEventListener('online', function () { self.send(event, toolId); }, { once: true });
                return false;
            }

            this._done[event + ':' + (toolId || '')] = true;
            var payload = JSON.stringify({ event: event, tool: toolId || '' });

            try {
                if (global.navigator.sendBeacon) {
                    return global.navigator.sendBeacon(
                        this.endpoint + '/api/track',
                        new Blob([payload], { type: 'text/plain;charset=UTF-8' })
                    );
                }
                global.fetch(this.endpoint + '/api/track', {
                    method: 'POST',
                    body: payload,
                    keepalive: true,
                    mode: 'no-cors',
                    headers: { 'Content-Type': 'text/plain;charset=UTF-8' }
                });
                return true;
            } catch (err) {
                return false;
            }
        }
    };

    ET.Stats = Stats;
})(window);
