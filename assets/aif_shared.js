(() => {
  'use strict';
  const AIF = window.AIF || (window.AIF = {});

  function ensureToastStack() {
    let stack = document.querySelector('.aif-toast-stack');
    if (!stack) {
      stack = document.createElement('div');
      stack.className = 'aif-toast-stack';
      stack.setAttribute('aria-live', 'polite');
      stack.setAttribute('aria-relevant', 'additions');
      document.body.appendChild(stack);
    }
    return stack;
  }

  function showToast(message, type = 'success', timeout = 3000) {
    if (!document.body) return;
    const el = document.createElement('div');
    const normalized = type === 'default' ? 'info' : String(type || 'success');
    el.className = `aif-toast aif-toast-${normalized}${normalized === 'error' ? ' is-error' : ''}`;
    el.textContent = String(message || '');
    ensureToastStack().appendChild(el);
    requestAnimationFrame(() => el.classList.add('is-visible'));
    window.setTimeout(() => {
      el.classList.remove('is-visible');
      window.setTimeout(() => el.remove(), 220);
    }, timeout);
  }

  function showSkeleton(target, count = 3) {
    const el = typeof target === 'string' ? document.querySelector(target) : target;
    if (!el) return;
    const rows = Math.max(1, Number(count) || 3);
    el.innerHTML = Array.from({ length: rows }, () => '<div class="aif-skeleton"><div class="aif-skeleton-line"></div><div class="aif-skeleton-line" style="width:72%"></div></div>').join('');
    el.setAttribute('aria-busy', 'true');
  }

  function clearBusy(target) {
    const el = typeof target === 'string' ? document.querySelector(target) : target;
    if (el) el.removeAttribute('aria-busy');
  }

  function initIconFallback() {
    const markFallback = () => document.documentElement.classList.add('aif-icons-fallback');
    const probe = document.createElement('i');
    probe.className = 'fa fa-check';
    probe.style.cssText = 'position:absolute;left:-9999px;top:-9999px;';
    document.body.appendChild(probe);
    const family = getComputedStyle(probe, '::before').fontFamily || getComputedStyle(probe).fontFamily || '';
    const content = getComputedStyle(probe, '::before').content || '';
    probe.remove();
    if (!/Font Awesome/i.test(family) || content === 'none' || content === 'normal') markFallback();
  }

  AIF.showToast = showToast;
  AIF.showSkeleton = showSkeleton;
  AIF.clearBusy = clearBusy;
  window.showToast = showToast;
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initIconFallback, { once: true }); else initIconFallback();
})();
