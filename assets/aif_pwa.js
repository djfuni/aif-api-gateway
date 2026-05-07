(() => {
  'use strict';
  if (!('serviceWorker' in navigator)) return;

  const AIF = window.AIF || {};
  let deferredInstall = null;

  function mountInstallPrompt() {
    if (document.querySelector('.aif-install-prompt')) return document.querySelector('.aif-install-prompt');
    const box = document.createElement('div');
    box.className = 'aif-install-prompt';
    box.innerHTML = '<div><strong>安装 NewAPI M3</strong><small>添加到桌面，获得更接近原生应用的体验。</small></div><button class="aif-install-accept" type="button">添加</button><button class="aif-install-dismiss" type="button" aria-label="稍后再说">稍后</button>';
    document.body.appendChild(box);
    box.querySelector('.aif-install-accept')?.addEventListener('click', async () => {
      if (!deferredInstall) return;
      deferredInstall.prompt();
      try { await deferredInstall.userChoice; } catch {}
      deferredInstall = null;
      box.classList.remove('is-visible');
    });
    box.querySelector('.aif-install-dismiss')?.addEventListener('click', () => box.classList.remove('is-visible'));
    return box;
  }

  window.addEventListener('beforeinstallprompt', event => {
    event.preventDefault();
    deferredInstall = event;
    window.setTimeout(() => mountInstallPrompt().classList.add('is-visible'), 800);
  });

  window.addEventListener('online', () => AIF.showToast?.('网络已恢复，离线请求会自动重试。', 'success'));
  window.addEventListener('offline', () => AIF.showToast?.('当前处于离线状态，部分请求会暂存。', 'warning'));

  navigator.serviceWorker.register('sw.js').catch(err => console.warn('[PWA] service worker registration failed', err));
})();
