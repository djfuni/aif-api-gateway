/* Extracted from developer-plan.html on 20260506-modular. */
(() => {
    'use strict';

    const TOTAL_POOL = 100000000000000;
    const API = {
      overview: 'ai_api_console_api.php?action=developer_plan_overview',
      submit: 'ai_api_console_api.php?action=submit_developer_application'
    };
    const state = { overview: null, countdownEnd: null };
    const $ = (selector, root = document) => root.querySelector(selector);
    const $$ = (selector, root = document) => Array.from(root.querySelectorAll(selector));
    const esc = value => String(value ?? '').replace(/[&<>"']/g, ch => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[ch]));

    function fmtTokens(value) {
      const n = Number(value || 0);
      if (n >= 1000000000000) return (n / 1000000000000).toFixed(2).replace(/\.00$/, '') + 'T';
      if (n >= 100000000) return (n / 100000000).toFixed(2).replace(/\.00$/, '') + '亿';
      if (n >= 1000000) return (n / 1000000).toFixed(2).replace(/\.00$/, '') + 'M';
      if (n >= 10000) return (n / 10000).toFixed(1).replace(/\.0$/, '') + '万';
      return String(n);
    }

    function animateNumber(el, target, duration = 1800) {
      if (!el) return;
      const from = Number(el.dataset.current || 0);
      const to = Math.max(0, Number(target || 0));
      const start = performance.now();
      el.dataset.current = String(to);
      function tick(now) {
        const progress = Math.min((now - start) / duration, 1);
        const eased = 1 - Math.pow(1 - progress, 3);
        el.textContent = Math.floor(from + (to - from) * eased).toLocaleString();
        if (progress < 1) requestAnimationFrame(tick);
      }
      requestAnimationFrame(tick);
    }

    async function request(url, options = {}) {
      const response = await fetch(url, {
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', ...(options.headers || {}) },
        ...options
      });
      const text = await response.text();
      let data = {};
      try { data = text ? JSON.parse(text) : {}; } catch { data = { ok: false, msg: text || '接口返回异常' }; }
      if (!response.ok || data.ok === false || data.status === 'error') {
        throw new Error(data.msg || data.message || `请求失败：HTTP ${response.status}`);
      }
      return data;
    }

    function initSpotlight() {
      const spotlight = $('#spotlight');
      if (!spotlight) return;
      const move = event => {
        spotlight.style.left = `${event.clientX}px`;
        spotlight.style.top = `${event.clientY}px`;
        spotlight.classList.add('is-active');
      };
      document.addEventListener('mousemove', move, { passive: true });
      document.addEventListener('mouseleave', () => spotlight.classList.remove('is-active'));

      $$('.step-card, .form-card, .side-card, .faq-section details').forEach(card => {
        card.addEventListener('mousemove', event => {
          const rect = card.getBoundingClientRect();
          card.style.setProperty('--card-x', `${event.clientX - rect.left}px`);
          card.style.setProperty('--card-y', `${event.clientY - rect.top}px`);
        }, { passive: true });
      });
    }

    function initMatrix() {
      const canvas = $('#matrixCanvas');
      if (!canvas) return;
      const ctx = canvas.getContext('2d');
      const chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ%$#@&*'.split('');
      const fontSize = 10;
      let drops = [];
      let columns = 0;

      function resize() {
        const ratio = Math.min(window.devicePixelRatio || 1, 2);
        canvas.width = Math.floor(window.innerWidth * ratio);
        canvas.height = Math.floor(window.innerHeight * ratio);
        canvas.style.width = window.innerWidth + 'px';
        canvas.style.height = window.innerHeight + 'px';
        ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
        columns = Math.ceil(window.innerWidth / fontSize);
        drops = Array.from({ length: columns }, () => Math.random() * window.innerHeight / fontSize);
      }

      function draw() {
        ctx.fillStyle = 'rgba(0, 0, 0, 0.055)';
        ctx.fillRect(0, 0, window.innerWidth, window.innerHeight);
        ctx.font = `${fontSize}px monospace`;
        for (let i = 0; i < drops.length; i++) {
          const text = chars[(Math.random() * chars.length) | 0];
          const x = i * fontSize;
          const y = drops[i] * fontSize;
          ctx.fillStyle = Math.random() > 0.965 ? 'rgba(255,255,255,0.35)' : 'rgba(255,255,255,0.13)';
          ctx.fillText(text, x, y);
          if (y > window.innerHeight && Math.random() > 0.975) drops[i] = 0;
          drops[i]++;
        }
      }

      let last = 0;
      function loop(ts) {
        if (ts - last > 36) {
          draw();
          last = ts;
        }
        requestAnimationFrame(loop);
      }
      resize();
      window.addEventListener('resize', resize);
      requestAnimationFrame(loop);
    }

    function initCountdown() {
      state.countdownEnd = new Date();
      state.countdownEnd.setDate(state.countdownEnd.getDate() + 90);
      function update() {
        const el = $('#countdownTimer');
        if (!el) return;
        const diff = state.countdownEnd - new Date();
        if (diff <= 0) { el.textContent = '已结束'; return; }
        const days = Math.floor(diff / 86400000);
        const hours = Math.floor((diff % 86400000) / 3600000);
        const mins = Math.floor((diff % 3600000) / 60000);
        const secs = Math.floor((diff % 60000) / 1000);
        el.textContent = `${days}天 ${String(hours).padStart(2,'0')}:${String(mins).padStart(2,'0')}:${String(secs).padStart(2,'0')}`;
      }
      update();
      setInterval(update, 1000);
    }

    function initShare() {
      const btn = $('#shareBtn');
      if (!btn) return;
      btn.addEventListener('click', async () => {
        const shareData = { title: document.title, text: 'NewAPI M3 百万亿 Token 创造者激励计划', url: location.href };
        try {
          if (navigator.share) await navigator.share(shareData);
          else {
            await navigator.clipboard.writeText(location.href);
            btn.textContent = '已复制';
            setTimeout(() => btn.textContent = '分享', 1400);
          }
        } catch (_) {}
      });
    }

    function initLanguageButton() {
      const btn = $('#langBtn');
      if (!btn) return;
      btn.addEventListener('click', () => {
        btn.textContent = btn.textContent === 'EN' ? '中文' : 'EN';
      });
    }

    function setStatus(message = '', type = '') {
      const box = $('#devApplyStatus');
      if (!box) return;
      box.textContent = message;
      box.classList.toggle('is-visible', !!message);
      box.classList.toggle('is-error', type === 'error');
      box.classList.toggle('is-ok', type === 'ok');
    }

    function fillPackages(packages = []) {
      const select = $('#devPackageSelect');
      if (!select) return;
      const visible = packages.filter(pkg => Number(pkg.tokens || 0) > 0 && pkg.enabled !== false);
      select.innerHTML = '<option value="">由管理员评估决定</option>' + visible.map(pkg => {
        const kind = pkg.kind === 'subscription' ? '月度套餐' : (pkg.kind === 'trial' ? '试用包' : '加量包');
        return `<option value="${esc(pkg.id)}">${esc(pkg.title || pkg.id)} · ${fmtTokens(pkg.tokens)} · ${kind}</option>`;
      }).join('');
    }

    function statusLabel(status) {
      return { pending: '待审核', approved: '已通过', rejected: '未通过' }[status] || status || '待审核';
    }

    function renderApplications(apps = []) {
      const box = $('#devMyApplications');
      if (!box) return;
      if (!state.overview?.logged_in) {
        box.innerHTML = '<p class="record-empty">登录后可查看你的申请状态。</p>';
        return;
      }
      if (!apps.length) {
        box.innerHTML = '<p class="record-empty">暂未提交申请。提交后，审核状态会显示在这里。</p>';
        return;
      }
      box.innerHTML = apps.map(app => {
        const status = String(app.status || 'pending');
        const grant = status === 'approved' && Number(app.granted_tokens || 0) > 0
          ? `<p>已发放：${esc(app.granted_package_title || app.granted_package_id)} · ${fmtTokens(app.granted_tokens)} Token</p>` : '';
        const note = app.admin_note ? `<p>审核备注：${esc(app.admin_note)}</p>` : '';
        return `<article class="record-item">
          <span class="tag ${esc(status)}">${esc(statusLabel(status))}</span>
          <strong>${esc(app.project_name)}</strong>
          <p>提交时间：${esc(app.created_at || '')}</p>
          ${grant}${note}
        </article>`;
      }).join('');
    }

    function renderOverview(data) {
      state.overview = data;
      const stats = data.stats || {};
      const granted = Math.max(0, Number(stats.granted_tokens || 0));
      const remaining = Math.max(0, TOTAL_POOL - granted);
      const remainingPct = Math.max(0, Math.min(100, (remaining / TOTAL_POOL) * 100));

      const heroToken = $('#heroTokenNumber');
      if (heroToken && !heroToken.dataset.ready) {
        animateNumber(heroToken, remaining, 2200);
        heroToken.dataset.ready = '1';
      } else if (heroToken) {
        heroToken.dataset.current = String(remaining);
        heroToken.textContent = remaining.toLocaleString();
      }
      const bar = $('#remainingBar');
      if (bar) bar.style.width = `${remainingPct}%`;
      const statGranted = $('#statGranted');
      if (statGranted) statGranted.textContent = fmtTokens(granted);
      const statPending = $('#statPending');
      if (statPending) statPending.textContent = String(stats.pending ?? '--');

      fillPackages(data.packages || []);
      renderApplications(data.applications || []);

      const hint = $('#devLoginHint');
      const form = $('#devApplicationForm');
      const submit = form?.querySelector('button[type="submit"]');
      const email = $('#devContactEmail');
      if (data.logged_in) {
        const user = data.user || {};
        if (hint) hint.innerHTML = `当前账号：<strong>${esc(user.nickname || user.username || '已登录')}</strong>。提交后管理员会在后台审核。`;
        if (email && !email.value && user.email) email.value = user.email;
        if (submit) submit.disabled = false;
        form?.querySelectorAll('input,textarea,select').forEach(el => { el.disabled = false; });
      } else {
        if (hint) hint.innerHTML = '请先 <a href="index.html">登录或注册本站账号</a>，管理员通过后才能把 Token 发放到你的账户。';
        if (submit) submit.disabled = true;
        form?.querySelectorAll('input,textarea,select').forEach(el => { if (el.type !== 'checkbox') el.disabled = true; });
      }
    }

    async function loadOverview() {
      try {
        const data = await request(API.overview);
        renderOverview(data);
      } catch (error) {
        renderOverview({ stats: { granted_tokens: 0, pending: 0 }, logged_in: false, packages: [], applications: [] });
        setStatus(error.message || '加载失败', 'error');
      }
    }

    async function submitApplication(event) {
      event.preventDefault();
      const form = event.currentTarget;
      const btn = form.querySelector('button[type="submit"]');
      if (!state.overview?.logged_in) {
        setStatus('请先登录或注册本站账号后再提交申请。', 'error');
        return;
      }
      const payload = Object.fromEntries(new FormData(form).entries());
      try {
        btn.disabled = true;
        setStatus('正在提交申请...');
        const data = await request(API.submit, { method: 'POST', body: JSON.stringify(payload) });
        setStatus(data.msg || '申请已提交。', 'ok');
        form.reset();
        if (data.stats) state.overview.stats = data.stats;
        renderOverview({ ...state.overview, applications: data.applications || [] });
      } catch (error) {
        setStatus(error.message || '提交失败', 'error');
      } finally {
        btn.disabled = false;
      }
    }

    document.addEventListener('DOMContentLoaded', () => {
      initSpotlight();
      initMatrix();
      initCountdown();
      initShare();
      initLanguageButton();
      $('#devApplicationForm')?.addEventListener('submit', submitApplication);
      loadOverview();
    });
  })();
