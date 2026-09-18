/* ═══════════════════════════════════════════════════════════════
   site.js — 前台脚本
   ═══════════════════════════════════════════════════════════════
   原则：渐进增强。无 JS 时页面仍可用，JS 只做体验优化。
   零依赖：不用任何框架或 CDN。

   P0 阶段仅含：CSRF token 注入 + 统计上报骨架（P1 启用）
   ═══════════════════════════════════════════════════════════════ */

(function () {
  'use strict';

  /**
   * 统计上报端点。若页面需要上报事件，读取 meta 中的 token。
   *
   * 上报失败一律静默：统计是次要功能，绝不能因网络问题
   * 在用户控制台留下红色报错或阻塞页面交互。
   */
  function track(eventType, payload) {
    var token = document.querySelector('meta[name="csrf-token"]');
    var body = Object.assign(
      { event: eventType },
      payload || {}
    );

    var headers = { 'Content-Type': 'application/json' };
    if (token) {
      headers['X-CSRF-Token'] = token.getAttribute('content') || '';
    }

    try {
      // keepalive 让请求在页面卸载（如点击下载跳转）时仍能发出
      fetch('/api/track', {
        method: 'POST',
        headers: headers,
        body: JSON.stringify(body),
        keepalive: true,
        credentials: 'same-origin'
      }).catch(function () { /* 静默 */ });
    } catch (err) {
      /* 静默：老旧浏览器可能不支持 fetch */
    }
  }

  /**
   * 搜索结果框：阻止空查询提交，避免跳转到无意义的空结果页。
   */
  function initSearchGuard() {
    var forms = document.querySelectorAll('form[role="search"]');
    Array.prototype.forEach.call(forms, function (form) {
      form.addEventListener('submit', function (e) {
        var input = form.querySelector('input[name="q"]');
        if (input && input.value.trim() === '') {
          e.preventDefault();
          input.focus();
        }
      });
    });
  }

  function init() {
    initSearchGuard();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  // 暴露给后续模块（P1 的工具卡片点击上报）
  window.EduTools = { track: track };
})();
