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
   * 网盘中间页：复制提取码。
   */
  function initCopyButton() {
    var buttons = document.querySelectorAll('[data-copy]');
    Array.prototype.forEach.call(buttons, function (btn) {
      btn.addEventListener('click', function () {
        var target = document.querySelector(btn.getAttribute('data-copy'));
        if (!target) {
          return;
        }
        var text = target.textContent || '';
        var done = function () {
          var old = btn.innerHTML;
          btn.innerHTML = '已复制';
          setTimeout(function () { btn.innerHTML = old; }, 1500);
        };
        if (navigator.clipboard && navigator.clipboard.writeText) {
          navigator.clipboard.writeText(text).then(done, function () {});
        } else {
          var ta = document.createElement('textarea');
          ta.value = text;
          ta.style.position = 'fixed';
          ta.style.opacity = '0';
          document.body.appendChild(ta);
          ta.select();
          try { document.execCommand('copy'); done(); } catch (e) {}
          document.body.removeChild(ta);
        }
      });
    });
  }

  /**
   * 网盘中间页：跳转倒计时（防直接抓链 + 传递「认真做站」信号）。
   * 无 JS 时 noscript 直接给出链接，不影响可用性。
   */
  function initCountdown() {
    var links = document.querySelectorAll('[data-countdown]');
    Array.prototype.forEach.call(links, function (link) {
      var seconds = parseInt(link.getAttribute('data-countdown'), 10);
      if (isNaN(seconds) || seconds < 1) {
        return;
      }
      var textEl = link.querySelector('.netdisk-go-text');
      var label = '前往网盘';
      link.classList.add('is-waiting');
      if (link.dataset) { link.dataset.readyLabel = label; }
      link.setAttribute('aria-disabled', 'true');

      var remain = seconds;
      var tick = function () {
        if (remain > 0) {
          if (textEl) {
            textEl.textContent = '请稍候 ' + remain + ' 秒…';
          }
          remain--;
          setTimeout(tick, 1000);
          return;
        }
        link.classList.remove('is-waiting');
        link.removeAttribute('aria-disabled');
        if (textEl) {
          textEl.textContent = label;
        }
      };
      tick();
    });
  }

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

  /**
   * 详情页在线预览：点击骨架区后按需加载 iframe（懒加载，不拖慢首屏）。
   */
  function initLazyPreview() {
    var boxes = document.querySelectorAll('[data-lazy-iframe]');
    Array.prototype.forEach.call(boxes, function (box) {
      var src = box.getAttribute('data-lazy-iframe');
      if (!src) {
        return;
      }
      box.addEventListener('click', function () {
        if (box.querySelector('iframe')) {
          return;
        }
        var frame = document.createElement('iframe');
        frame.className = 'tool-preview-frame';
        frame.setAttribute('title', '在线预览');
        frame.setAttribute('src', src);
        frame.setAttribute('allow', 'fullscreen');
        box.innerHTML = '';
        box.appendChild(frame);
      });
    });
  }

  /**
   * 危险操作确认（后台删除等表单 data-confirm 属性）。
   */
  function initConfirmForms() {
    var forms = document.querySelectorAll('form[data-confirm]');
    Array.prototype.forEach.call(forms, function (form) {
      form.addEventListener('submit', function (e) {
        if (!window.confirm(form.getAttribute('data-confirm') || '确认执行？')) {
          e.preventDefault();
        }
      });
    });
  }

  function init() {
    initSearchGuard();
    initCopyButton();
    initCountdown();
    initLazyPreview();
    initConfirmForms();
    initAnnouncements();
  }

  /**
   * 公告：通栏关闭记忆（内容指纹）+ 公告中心弹层。
   * 改文案 → 指纹变化 → 旧访客会重新看到（edupick 关键设计）。
   */
  function initAnnouncements() {
    var KEY = 'et_notice_seen';
    var seen = {};
    try { seen = JSON.parse(localStorage.getItem(KEY) || '{}') || {}; } catch (e) { seen = {}; }

    var bar = document.querySelector('.notice-bar');
    if (bar) {
      var closable = bar.getAttribute('data-closable') === '1';
      var items = bar.querySelectorAll('[data-notice-finger]');
      var allSeen = true;
      Array.prototype.forEach.call(items, function (item) {
        var finger = item.getAttribute('data-notice-finger');
        if (finger && !seen[finger]) {
          allSeen = false;
        }
        if (finger && seen[finger]) {
          item.style.display = 'none';
        }
      });
      if (closable) {
        var closeBtn = bar.querySelector('.notice-close');
        if (closeBtn) {
          closeBtn.addEventListener('click', function () {
            Array.prototype.forEach.call(items, function (item) {
              var finger = item.getAttribute('data-notice-finger');
              if (finger) {
                seen[finger] = 1;
              }
              item.style.display = 'none';
            });
            try { localStorage.setItem(KEY, JSON.stringify(seen)); } catch (e) {}
            bar.style.display = 'none';
          });
        }
      }
      if (allSeen && items.length > 0) {
        bar.style.display = 'none';
      }
    }

    // 公告中心弹层
    var trigger = document.querySelector('[data-notice-center]');
    var modal = document.getElementById('notice-center');
    if (trigger && modal) {
      trigger.addEventListener('click', function (e) {
        e.preventDefault();
        modal.style.display = 'flex';
      });
      var close = modal.querySelector('.notice-modal-close');
      if (close) {
        close.addEventListener('click', function () {
          modal.style.display = 'none';
        });
      }
      modal.addEventListener('click', function (e) {
        if (e.target === modal) {
          modal.style.display = 'none';
        }
      });
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  // 暴露给后续模块（P1 的工具卡片点击上报）
  window.KeeTools = { track: track };
})();
