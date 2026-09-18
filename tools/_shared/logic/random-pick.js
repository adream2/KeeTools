/*!
 * random-pick.js — 随机抽取（加权 / 去重 / 候选池）
 *
 * 共享逻辑源。改这里，然后跑：python scripts/sync_shared.py
 * 依赖：无。
 * 全局：ET.Pick
 */
(function (global) {
    'use strict';

    /**
     * 按权重随机返回索引。
     * weights: [num]，每项 ≥1。
     */
    function weightedIndex(weights) {
        var total = 0;
        for (var i = 0; i < weights.length; i++) {
            total += Math.max(1, weights[i] || 1);
        }
        var r = Math.random() * total;
        var acc = 0;
        for (var j = 0; j < weights.length; j++) {
            acc += Math.max(1, weights[j] || 1);
            if (r < acc) { return j; }
        }
        return weights.length - 1;
    }

    /**
     * 构建候选池。
     * items: [{name, weight, id?}]
     * opts: { noRepeat: bool, picked: {name: true} }  picked 为已抽中标记
     * 返回 [{ index, name, weight, id }]（index 为在 items 中的下标）
     */
    function buildCandidates(items, opts) {
        opts = opts || {};
        var out = [];
        var picked = opts.picked || {};
        for (var i = 0; i < items.length; i++) {
            var it = items[i];
            if (!it || !it.name) { continue; }
            if (opts.noRepeat && picked[it.name]) { continue; }
            out.push({ index: i, name: it.name, weight: Math.max(1, it.weight || 1), id: it.id || '' });
        }
        return out;
    }

    /**
     * 从候选池抽 1 个。
     * candidates: buildCandidates 的返回值。
     * opts: { weighted: bool }
     * 返回候选对象；池为空返回 null。
     */
    function pickOne(candidates, opts) {
        if (!candidates || !candidates.length) { return null; }
        opts = opts || {};
        var idx;
        if (opts.weighted) {
            var weights = [];
            for (var i = 0; i < candidates.length; i++) { weights.push(candidates[i].weight); }
            idx = weightedIndex(weights);
        } else {
            idx = Math.floor(Math.random() * candidates.length);
        }
        return candidates[idx];
    }

    /**
     * 洗牌（Fisher–Yates），返回新数组，不改原数组。
     */
    function shuffle(arr) {
        var out = arr.slice();
        for (var i = out.length - 1; i > 0; i--) {
            var j = Math.floor(Math.random() * (i + 1));
            var tmp = out[i];
            out[i] = out[j];
            out[j] = tmp;
        }
        return out;
    }

    /**
     * 抽 N 个（不重复），加权时按权重有放回地抽再去重。
     */
    function pickMany(candidates, n, opts) {
        opts = opts || {};
        var pool = candidates.slice();
        var out = [];
        n = Math.min(n, pool.length);
        for (var k = 0; k < n; k++) {
            var one = pickOne(pool, opts);
            if (!one) { break; }
            out.push(one);
            pool = pool.filter(function (c) { return c.name !== one.name; });
        }
        return out;
    }

    global.ET = global.ET || {};
    global.ET.Pick = {
        weightedIndex: weightedIndex,
        buildCandidates: buildCandidates,
        pickOne: pickOne,
        pickMany: pickMany,
        shuffle: shuffle
    };
})(window);
