/*!
 * et-util.js — 工具通用小工具函数
 *
 * 共享逻辑源。改这里，然后跑：python scripts/sync_shared.py
 * 禁止手工编辑工具 HTML 内 ET:INLINE 之间的内容。
 *
 * 兼容性：ES5，Win7 + 老版 Chrome / 360 可运行。
 * 依赖：无。
 * 全局：挂载 window.ET（单一命名空间，不污染其他全局）
 */
(function (global) {
    'use strict';

    var ET = global.ET || {};

    /** 按 id 取元素 */
    ET.el = function (id) {
        return document.getElementById(id);
    };

    /** 按选择器取首个元素 */
    ET.qs = function (selector, root) {
        return (root || document).querySelector(selector);
    };

    /** 按选择器取全部元素，返回真数组 */
    ET.qsa = function (selector, root) {
        var list = (root || document).querySelectorAll(selector);
        var out = [];
        for (var i = 0; i < list.length; i++) {
            out.push(list[i]);
        }
        return out;
    };

    /** 创建元素 */
    ET.create = function (tag, className, text) {
        var node = document.createElement(tag);
        if (className) {
            node.className = className;
        }
        if (text !== undefined && text !== null) {
            node.textContent = String(text);
        }
        return node;
    };

    /** HTML 转义（用于把用户数据放进 HTML 字符串时） */
    ET.escapeHtml = function (value) {
        return String(value === undefined || value === null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    };

    /** 触发自定义事件（老浏览器用 createEvent 降级） */
    ET.emit = function (target, name, detail) {
        var event;
        if (typeof global.CustomEvent === 'function') {
            event = new global.CustomEvent(name, { detail: detail });
        } else {
            event = document.createEvent('CustomEvent');
            event.initCustomEvent(name, false, false, detail);
        }
        target.dispatchEvent(event);
    };

    /**
     * localStorage 读写（带前缀与异常兜底）
     * 前缀规范：et_{toolId}_
     */
    ET.store = function (toolId) {
        var prefix = 'et_' + toolId + '_';

        function available() {
            try {
                var probe = prefix + '__probe__';
                global.localStorage.setItem(probe, '1');
                global.localStorage.removeItem(probe);
                return true;
            } catch (err) {
                return false;
            }
        }

        var ok = available();

        return {
            /** 读取，解析失败返回 fallback */
            get: function (key, fallback) {
                if (!ok) {
                    return fallback;
                }
                try {
                    var raw = global.localStorage.getItem(prefix + key);
                    if (raw === null) {
                        return fallback;
                    }
                    return JSON.parse(raw);
                } catch (err) {
                    return fallback;
                }
            },
            /** 写入，失败静默（隐私模式 / 配额满） */
            set: function (key, value) {
                if (!ok) {
                    return false;
                }
                try {
                    global.localStorage.setItem(prefix + key, JSON.stringify(value));
                    return true;
                } catch (err) {
                    return false;
                }
            },
            /** 删除 */
            remove: function (key) {
                if (!ok) {
                    return;
                }
                try {
                    global.localStorage.removeItem(prefix + key);
                } catch (err) {
                    /* 静默 */
                }
            }
        };
    };

    /** DOM 就绪回调（脚本在 head 内也能安全调用） */
    ET.ready = function (callback) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', callback);
        } else {
            callback();
        }
    };

    global.ET = ET;
})(window);
