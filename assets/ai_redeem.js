(() => {
  'use strict';

  const API = {
    overview: 'ai_api_console_api.php?action=overview',
    redeem: 'ai_api_console_api.php?action=redeem_code'
  };

  const state = { overview: null };
  const $ = selector => document.querySelector(selector);

  const fmt = value => Number(value || 0).toLocaleString('zh-CN');
  const compact = value => {
    const n = Number(value || 0);
    if (n >= 100000000) return (n / 100000000).toFixed(1).replace(/\.0$/, '') + '亿';
    if (n >= 10000) return (n / 10000).toFixed(1).replace(/\.0$/, '') + '万';
    return fmt(n);
  };
  const esc = value => String(value ?? '').replace(/[&<>"']/g, ch => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[ch]));

  async function request(url, options = {}) {
    const res = await fetch(url, {
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      ...options
    });
    const text = await res.text();
    let data = {};
    try { data = text ? JSON.parse(text) : {}; } catch { data = { ok: false, msg: text || `HTTP ${res.status}` }; }
    if (!res.ok) throw new Error(data.msg || data?.error?.message || `HTTP ${res.status}`);
    return data;
  }

  function toast(message, type = 'default') {
    if (window.AIF?.showToast) return window.AIF.showToast(message, type === 'default' ? 'info' : type);
    console.log('[toast]', type, message);
  }

  function showResult(message, type = 'success') {
    const box = $('#redeemResult');
    if (!box) return;
    box.textContent = message;
    box.classList.add('is-visible');
    box.classList.toggle('is-error', type === 'error');
  }

  function renderSummary() {
    const data = state.overview || {};
    const loggedIn = !!data.logged_in;
    const user = data.user || {};
    const records = Array.isArray(data.redeem_records) ? data.redeem_records : [];
    const wallet = data.wallet || {};
    const recentTotal = records.reduce((sum, row) => sum + Number(row.tokens || 0), 0);

    $('#redeemAccountName').textContent = loggedIn ? (user.nickname || user.username || '已登录') : '未登录';
    $('#redeemAccountHint').textContent = loggedIn ? `UID ${user.id || '--'} · 兑换将发放到该账号` : '请先登录本站账号，再兑换 Token';
    $('#redeemBalance').textContent = loggedIn ? compact(wallet.balance_tokens || 0) : '--';
    $('#redeemRecordCount').textContent = fmt(records.length);
    $('#redeemRecentTotal').textContent = compact(recentTotal);
    $('#redeemBindTarget').textContent = loggedIn ? (user.nickname || user.username || '当前登录账号') : '登录后才可兑换';

    const input = $('#redeemCodeInput');
    const btn = $('#redeemSubmitBtn');
    if (input) input.disabled = !loggedIn;
    if (btn) btn.disabled = !loggedIn;
    $('#redeemLoginPrompt')?.classList.toggle('is-visible', !loggedIn);
  }

  function renderRecords() {
    const box = $('#redeemRecords');
    if (!box) return;
    const rows = Array.isArray(state.overview?.redeem_records) ? state.overview.redeem_records : [];
    if (!rows.length) {
      box.innerHTML = '<p class="aif-empty">暂无兑换记录，兑换成功后会展示在这里。</p>';
      return;
    }
    box.innerHTML = rows.map(row => `
      <div class="aif-redeem-record">
        <div>
          <b>${esc(row.title || '兑换码到账')}</b>
          <small>${esc(row.code_preview || '')} · ${esc(row.created_at || '')}</small>
        </div>
        <span>+${esc(compact(row.tokens || 0))}</span>
      </div>
    `).join('');
  }

  async function load() {
    const errorBox = $('#redeemError');
    try {
      if (errorBox) errorBox.hidden = true;
      state.overview = await request(API.overview);
      renderSummary();
      renderRecords();
      window.AIF?.clearBusy?.('#redeemRecords');
    } catch (err) {
      if (errorBox) {
        errorBox.hidden = false;
        errorBox.textContent = err.message || '兑换中心数据加载失败';
      }
      showResult(err.message || '兑换中心数据加载失败', 'error');
    }
  }

  async function redeemCode() {
    const codeInput = $('#redeemCodeInput');
    const submitBtn = $('#redeemSubmitBtn');
    const code = String(codeInput?.value || '').trim().toUpperCase();
    if (!state.overview?.logged_in) {
      showResult('请先登录本站账号，再进行兑换。', 'error');
      toast('请先登录后再兑换', 'error');
      return;
    }
    if (!code) {
      showResult('请输入兑换码后再兑换。', 'error');
      toast('请输入兑换码', 'error');
      codeInput?.focus();
      return;
    }

    if (submitBtn) submitBtn.disabled = true;
    try {
      const data = await request(API.redeem, { method: 'POST', body: JSON.stringify({ code }) });
      const tokens = Number(data?.data?.record?.tokens || 0);
      if (codeInput) codeInput.value = '';
      showResult(`${data.msg || '兑换成功'}${tokens > 0 ? `，+${fmt(tokens)} Token 已到账。` : '。'}`);
      toast(data.msg || '兑换成功');
      await load();
    } catch (err) {
      showResult(err.message || '兑换失败，请稍后重试。', 'error');
      toast(err.message || '兑换失败', 'error');
    } finally {
      if (submitBtn) submitBtn.disabled = !state.overview?.logged_in;
    }
  }

  function bindEvents() {
    $('#redeemRefreshBtn')?.addEventListener('click', load);
    $('#redeemForm')?.addEventListener('submit', event => {
      event.preventDefault();
      redeemCode();
    });
    $('#redeemCodeInput')?.addEventListener('input', event => {
      const el = event.currentTarget;
      el.value = String(el.value || '').toUpperCase().replace(/\s+/g, '');
    });
  }

  bindEvents();
  load();
})();
