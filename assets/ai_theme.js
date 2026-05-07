(() => {
  'use strict';
  const THEMES = [
    { id: 'light', name: '亮色 M3', note: 'Material You light', icon: 'fa-sun' },
    { id: 'dark', name: '暗色 M3', note: '低亮度控制台', icon: 'fa-moon' },
    { id: 'system', name: '跟随系统', note: '自动匹配系统外观', icon: 'fa-circle-half-stroke' }
  ];
  const media = window.matchMedia?.('(prefers-color-scheme: dark)');
  const storedTheme = () => { try { return localStorage.getItem('aif_theme') || 'light'; } catch { return 'light'; } };
  const resolvedTheme = theme => theme === 'system' ? (media?.matches ? 'dark' : 'light') : (theme === 'dark' ? 'dark' : 'light');
  function applyTheme(theme = storedTheme()) {
    const chosen = THEMES.some(item => item.id === theme) ? theme : 'light';
    document.documentElement.dataset.theme = resolvedTheme(chosen);
    document.documentElement.dataset.themeChoice = chosen;
    try { localStorage.setItem('aif_theme', chosen); } catch {}
    document.querySelectorAll('[data-theme-choice]').forEach(btn => btn.classList.toggle('is-active', btn.dataset.themeChoice === chosen));
  }
  function template() {
    return `
      <button class="aif-theme-fab" type="button" aria-label="切换主题" aria-expanded="false"><i class="fa fa-circle-half-stroke"></i></button>
      <section class="aif-theme-panel" hidden>
        <div class="aif-theme-panel-head">
          <div><span>Theme</span><strong>外观主题</strong></div>
          <button type="button" class="aif-theme-close" aria-label="关闭"><i class="fa fa-times"></i></button>
        </div>
        <div class="aif-theme-list">
          ${THEMES.map(item => `<button type="button" class="aif-theme-choice" data-theme-choice="${item.id}"><span class="aif-theme-swatch"><i class="fa ${item.icon}"></i></span><span class="aif-theme-copy"><strong>${item.name}</strong><small>${item.note}</small></span></button>`).join('')}
        </div>
        <p class="aif-theme-tip"><i class="fa fa-check-circle"></i> 主题会保存在本地浏览器中。</p>
      </section>`;
  }
  function mount() {
    applyTheme();
    if (document.querySelector('.aif-theme-dock')) return;
    const dock = document.createElement('div');
    dock.className = 'aif-theme-dock';
    dock.innerHTML = template();
    document.body.appendChild(dock);
    const fab = dock.querySelector('.aif-theme-fab');
    const panel = dock.querySelector('.aif-theme-panel');
    const closeBtn = dock.querySelector('.aif-theme-close');
    const open = () => { panel.hidden = false; dock.classList.add('is-open'); fab.setAttribute('aria-expanded','true'); };
    const close = () => { panel.hidden = true; dock.classList.remove('is-open'); fab.setAttribute('aria-expanded','false'); };
    fab?.addEventListener('click', () => dock.classList.contains('is-open') ? close() : open());
    closeBtn?.addEventListener('click', close);
    dock.querySelectorAll('[data-theme-choice]').forEach(btn => btn.addEventListener('click', () => applyTheme(btn.dataset.themeChoice)));
    document.addEventListener('keydown', event => { if (event.key === 'Escape') close(); });
    media?.addEventListener?.('change', () => { if (storedTheme() === 'system') applyTheme('system'); });
  }
  window.AIF = window.AIF || {};
  window.AIF.applyTheme = applyTheme;
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', mount); else mount();
})();
