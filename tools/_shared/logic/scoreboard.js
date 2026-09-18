/*!
 * scoreboard.js — 计分板逻辑（多队伍 / 加减分 / 历史 / 撤销）
 *
 * 共享逻辑源。改这里，然后跑：python scripts/sync_shared.py
 * 依赖：无。
 * 全局：ET.Board
 *
 * 用法：
 *   var board = ET.Board.create({ teams: [{ name: '红队', score: 0 }], onChange: fn });
 *   board.teams                          // [{name, score, color}]
 *   board.addTeam('蓝队')                 // 返回新队伍
 *   board.removeTeam(i)
 *   board.renameTeam(i, '黄队')
 *   board.score(i, +100)                 // 加减分，写入历史
 *   board.undo()                         // 撤销最近一次计分
 *   board.resetScores(keepHistory)       // 分数清零
 *   board.clearHistory()
 *   board.history                        // [{time, team, delta, scoreAfter}]
 *   board.toJSON() / ET.Board.fromJSON(json, onChange)
 *
 * 颜色：自动分配自 8 色课堂友好调色板。
 */
(function (global) {
    'use strict';

    var PALETTE = [
        '#ef4444', '#3b82f6', '#22c55e', '#f59e0b',
        '#a855f7', '#ec4899', '#06b6d4', '#84cc16'
    ];

    var MAX_HISTORY = 500;

    function nowStamp() {
        var d = new Date();
        function p(n) { return (n < 10 ? '0' : '') + n; }
        return p(d.getHours()) + ':' + p(d.getMinutes()) + ':' + p(d.getSeconds());
    }

    function create(options) {
        var opt = options || {};
        var teams = [];
        var history = [];
        var onChange = opt.onChange || function () {};

        function normalizeTeam(t, i) {
            return {
                name: (t && t.name) || ('队伍 ' + (i + 1)),
                score: typeof (t && t.score) === 'number' ? t.score : 0,
                color: (t && t.color) || PALETTE[i % PALETTE.length]
            };
        }

        (opt.teams && opt.teams.length ? opt.teams : [null, null]).forEach(function (t, i) {
            teams.push(normalizeTeam(t, i));
        });

        function emit() {
            if (onChange) { onChange(); }
        }

        var api = {};

        api.teams = teams;
        api.history = history;

        api.addTeam = function (name) {
            if (teams.length >= 8) { return null; }
            var t = normalizeTeam({ name: name }, teams.length);
            teams.push(t);
            emit();
            return t;
        };

        api.removeTeam = function (i) {
            if (teams.length <= 1) { return false; }
            teams.splice(i, 1);
            emit();
            return true;
        };

        api.renameTeam = function (i, name) {
            var n = String(name || '').trim();
            if (!n) { return false; }
            teams[i].name = n;
            emit();
            return true;
        };

        api.setColor = function (i, color) {
            teams[i].color = color;
            emit();
        };

        /** 加减分并记历史 */
        api.score = function (i, delta, note) {
            delta = Math.round(delta || 0);
            if (!delta) { return; }
            var team = teams[i];
            team.score += delta;
            history.push({
                time: nowStamp(),
                team: team.name,
                delta: delta,
                scoreAfter: team.score,
                note: note || ''
            });
            if (history.length > MAX_HISTORY) { history.shift(); }
            emit();
        };

        /** 撤销最近一次计分 */
        api.undo = function () {
            var last = history.pop();
            if (!last) { return false; }
            for (var i = 0; i < teams.length; i++) {
                if (teams[i].name === last.team) {
                    teams[i].score -= last.delta;
                    break;
                }
            }
            emit();
            return true;
        };

        api.canUndo = function () { return history.length > 0; };

        api.resetScores = function (keepHistory) {
            for (var i = 0; i < teams.length; i++) { teams[i].score = 0; }
            if (!keepHistory) { history = []; api.history = history; }
            emit();
        };

        api.clearHistory = function () {
            history = [];
            api.history = history;
            emit();
        };

        api.toJSON = function () {
            return { teams: teams, history: history };
        };

        return api;
    }

    function fromJSON(json, onChange) {
        json = json || {};
        return create({ teams: json.teams, history: json.history, onChange: onChange });
    }

    global.ET = global.ET || {};
    global.ET.Board = { create: create, fromJSON: fromJSON, PALETTE: PALETTE };
})(window);
