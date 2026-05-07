(() => {
  'use strict';

  const API = Object.freeze({
    overview: 'ai_api_console_api.php?action=overview',
    createKey: 'ai_api_console_api.php?action=generate_key',
    createOrder: 'ai_api_console_api.php?action=create_order',
    chat: 'v1/chat/completions',
    liveModels: 'models_live.php',
    auth: 'auth.php?action='
  });

  const state = {
    view: 'home',
    overview: null,
    registry: null,
    registryPromise: null,
    consolePromise: null,
    models: [],
    selectedProvider: 'all',
    apiKey: localStorage.getItem('aif_ai_api_key') || '',
    messages: [],
    homeAuthTab: 'login'
  };

  const $ = (selector, root = document) => root.querySelector(selector);
  const $$ = (selector, root = document) => Array.from(root.querySelectorAll(selector));
  const esc = value => String(value ?? '').replace(/[&<>"']/g, ch => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[ch]));
  const debounce = (fn, delay = 160) => {
    let timer = 0;
    return (...args) => {
      clearTimeout(timer);
      timer = setTimeout(() => fn(...args), delay);
    };
  };

  const validViews = new Set(['home', 'chat', 'models', 'console']);
  function viewFromLocation() {
    const hash = decodeURIComponent((location.hash || '').replace(/^#/, '')).trim();
    if (validViews.has(hash)) return hash;
    const param = new URLSearchParams(location.search).get('view');
    return validViews.has(param) ? param : 'home';
  }

  function updateViewUrl(view, replace = false) {
    if (!validViews.has(view)) return;
    const next = `${location.pathname}${location.search}#${view}`;
    if (`${location.pathname}${location.search}${location.hash}` === next) return;
    const method = replace ? 'replaceState' : 'pushState';
    history[method]?.({ aifView: view }, '', next);
  }

  function toast(message, type = 'default') {
    if (window.AIF?.showToast) return window.AIF.showToast(message, type === 'default' ? 'info' : type);
    console.log('[toast]', type, message);
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
      throw new Error(data.msg || data.message || data?.error?.message || `请求失败：HTTP ${response.status}`);
    }
    return data;
  }

  async function authRequest(action, payload = {}) {
    const form = new FormData();
    Object.entries(payload).forEach(([key, value]) => form.append(key, value == null ? '' : String(value)));
    const response = await fetch(API.auth + encodeURIComponent(action), {
      method: 'POST',
      body: form,
      credentials: 'same-origin',
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    });
    const text = await response.text();
    let data = {};
    try { data = text ? JSON.parse(text) : {}; } catch { data = { ok: false, msg: text || '接口返回异常' }; }
    if (!response.ok || data.ok === false || data.status === 'error') {
      throw new Error(data.msg || data.message || `请求失败：HTTP ${response.status}`);
    }
    return data;
  }

  function fmtTokens(value) {
    const n = Number(value || 0);
    if (n >= 100000000) return (n / 100000000).toFixed(1).replace(/\.0$/, '') + '亿';
    if (n >= 1000000) return (n / 1000000).toFixed(2).replace(/\.00$/, '') + 'M';
    if (n >= 10000) return (n / 10000).toFixed(1).replace(/\.0$/, '') + '万';
    if (n >= 1000) return (n / 1000).toFixed(1).replace(/\.0$/, '') + 'K';
    return String(n);
  }

  function formPayload(form) {
    return Object.fromEntries(new FormData(form).entries());
  }

  function captchaUrl() {
    return `auth.php?action=captcha_svg&_=${Date.now()}${Math.random().toString(36).slice(2)}`;
  }

  function refreshCaptcha(tab = state.homeAuthTab) {
    const target = tab === 'register' ? $('#homeRegisterCaptcha') : $('#homeLoginCaptcha');
    if (target) target.src = captchaUrl();
  }

  function setAuthStatus(message = '', type = 'default') {
    const box = $('#homeAuthStatus');
    if (!box) return;
    box.textContent = message;
    box.classList.toggle('is-error', type === 'error');
    box.classList.toggle('is-ok', type === 'ok');
  }

  function switchHomeAuthTab(tab) {
    state.homeAuthTab = tab === 'register' ? 'register' : 'login';
    $$('[data-home-auth-tab]').forEach(btn => btn.classList.toggle('is-active', btn.dataset.homeAuthTab === state.homeAuthTab));
    $('#homeLoginForm')?.classList.toggle('is-active', state.homeAuthTab === 'login');
    $('#homeRegisterForm')?.classList.toggle('is-active', state.homeAuthTab === 'register');
    setAuthStatus('');
    refreshCaptcha(state.homeAuthTab);
  }

  function modelId(row) {
    if (row.runtime_id) return row.runtime_id;
    if (row.provider && row.id && !String(row.id).includes('::')) return `${row.provider}::${row.id}`;
    return row.id || row.model || '';
  }

  function flattenRegistry(registry) {
    const rows = [];
    Object.entries(registry?.providers || {}).forEach(([provider, cfg]) => {
      (cfg.models || []).forEach(model => rows.push({
        ...model,
        provider,
        provider_label: cfg.label || provider,
        configured: !!cfg.configured,
        runtime_id: `${provider}::${model.id}`,
        label: model.label || model.id
      }));
    });
    return rows;
  }

  function collectModels() {
    const registryRows = flattenRegistry(state.registry);
    const apiRows = (state.overview?.models || []).map(model => ({
      id: model.id,
      runtime_id: model.id,
      label: model.label || model.id,
      provider: String(model.provider || model.id || '').split('::')[0] || 'site',
      provider_label: model.provider || '站内服务',
      tags: [model.provider || 'site'],
      thinking: !!model.supports_thinking
    }));
    const seen = new Set();
    state.models = [...registryRows, ...apiRows].filter(row => {
      const id = modelId(row);
      if (!id || seen.has(id)) return false;
      seen.add(id);
      return true;
    });
  }

  function filteredModels() {
    collectModels();
    const search = ($('#modelSearchInput')?.value || '').trim().toLowerCase();
    return state.models.filter(row => {
      const id = modelId(row);
      const haystack = [id, row.label, row.provider, row.provider_label, ...(row.tags || [])].join(' ').toLowerCase();
      const providerOk = state.selectedProvider === 'all' || row.provider === state.selectedProvider || id.startsWith(`${state.selectedProvider}::`);
      return providerOk && (!search || haystack.includes(search));
    });
  }

  function renderModels() {
    const select = $('#modelSelect');
    const cards = $('#modelCards');
    const rows = filteredModels();

    if (select) {
      const previous = select.value;
      const chatRows = rows.filter(row => row.supports_chat !== false && !String(row.type || '').startsWith('audio'));
      select.innerHTML = chatRows.map(row => {
        const id = modelId(row);
        return `<option value="${esc(id)}">${esc(row.provider_label || row.provider)} · ${esc(row.label || row.id)}</option>`;
      }).join('');
      if (previous && Array.from(select.options).some(option => option.value === previous)) select.value = previous;
      if (!select.value && select.options.length) select.selectedIndex = 0;
    }

    if (cards) {
      cards.innerHTML = rows.map(row => {
        const id = modelId(row);
        const tagList = (row.tags || []).map(String);
        const tags = tagList.slice(0, 4).join(' · ');
        const nonChat = row.supports_chat === false || String(row.type || '').startsWith('audio') || tagList.some(tag => /tts|audio/i.test(tag));
        const status = row.configured === false ? ' · 未配置上游 Key' : '';
        return `<article class="aif-model-card${nonChat ? ' is-utility' : ''}">
          <span>${esc(row.provider_label || row.provider || 'Provider')}</span>
          <h3>${esc(row.label || row.id)}</h3>
          <p>${esc(id)}${tags ? ' · ' + esc(tags) : ''}${row.thinking ? ' · 支持推理' : ''}${nonChat ? ' · 音频接口' : ''}${status}</p>
          ${nonChat ? `<button type="button" data-copy-text="${esc(id)}">复制模型 ID</button>` : `<button type="button" data-use-model="${esc(id)}">使用该服务</button>`}
        </article>`;
      }).join('') || '<p class="aif-empty">没有匹配的服务，请切换平台或同步列表。</p>';
    }
  }

  async function loadRegistry(force = false) {
    if (!force && state.registry) return state.registry;
    if (!force && state.registryPromise) return state.registryPromise;
    state.registryPromise = request(API.liveModels + (force ? '?refresh=1' : ''))
      .then(data => {
        state.registry = data.registry || data;
        renderModels();
        return state.registry;
      })
      .catch(error => {
        console.warn(error);
        state.registry = { providers: {} };
        renderModels();
        return state.registry;
      })
      .finally(() => { state.registryPromise = null; });
    return state.registryPromise;
  }

  function ensureRegistry() {
    return state.registry ? Promise.resolve(state.registry) : loadRegistry(false);
  }

  function currentPlanKind() {
    return $('.aif-plan-tabs button[data-plan-kind].is-active')?.dataset.planKind || 'subscription';
  }

  function packageKindLabel(kind) {
    if (kind === 'subscription') return '月度订阅';
    if (kind === 'trial') return '免费试用';
    return '额度加量包';
  }

  function formatPrice(pkg) {
    const price = Number(pkg.price || 0);
    return price <= 0 ? '免费' : `¥${price}${pkg.kind === 'subscription' ? '/月' : ''}`;
  }

  function packageFeatures(pkg) {
    const features = Array.isArray(pkg.features) ? pkg.features : [];
    if (features.length) return features;
    const tokens = fmtTokens(pkg.tokens || 0);
    if (pkg.kind === 'subscription') return [`${tokens} 额度 / ${pkg.period_days || 30} 天`, '购买后立即到账', '可用于全部上架模型'];
    if (pkg.kind === 'trial') return [`${tokens} 额度`, '每个账号限领一次'];
    return [`${tokens} 额度`, '余额长期可用'];
  }

  function planCard(pkg, compact = false) {
    return `<article class="aif-plan-card${pkg.recommended ? ' is-recommended' : ''}${compact ? ' is-compact' : ''}">
      <div class="aif-plan-badge">${esc(pkg.badge || packageKindLabel(pkg.kind))}</div>
      <h3>${esc(pkg.title || pkg.id)}</h3>
      <p>${esc(pkg.description || '')}</p>
      <div class="aif-plan-price">${esc(formatPrice(pkg))}</div>
      <div class="aif-plan-tokens">${esc(fmtTokens(pkg.tokens || 0))} 额度${pkg.period_days ? ` · ${pkg.period_days} 天` : ''}</div>
      <ul>${packageFeatures(pkg).slice(0, compact ? 2 : 4).map(item => `<li>${esc(item)}</li>`).join('')}</ul>
      <button type="button" data-buy-package="${esc(pkg.id)}">${Number(pkg.price || 0) <= 0 ? '立即领取' : '选择套餐'}</button>
    </article>`;
  }

  function renderPlans() {
    const packages = state.overview?.packages || [];
    const board = $('#planCards');
    if (board) {
      const rows = packages.filter(pkg => (pkg.kind || 'topup') === currentPlanKind());
      board.innerHTML = rows.map(pkg => planCard(pkg)).join('') || '<p class="aif-empty">暂无该类型套餐。</p>';
    }

    const homePreview = $('#homePlanPreview');
    if (homePreview) {
      const picks = packages.filter(pkg => pkg.kind === 'subscription').slice(0, 3);
      homePreview.innerHTML = picks.map(pkg => planCard(pkg, true)).join('') || '<p class="aif-empty">套餐正在加载，请稍后刷新。</p>';
    }
  }

  function renderHome() {
    const data = state.overview || {};
    const user = data.user || null;
    const loggedIn = !!data.logged_in;
    const keys = Array.isArray(data.keys) ? data.keys : [];
    const usage = Array.isArray(data.usage) ? data.usage : [];
    const wallet = data.wallet || null;
    const activeSub = (data.subscriptions || []).find(item => (item.status || 'active') === 'active') || null;

    $('#homeUserName') && ($('#homeUserName').textContent = loggedIn ? (user?.nickname || user?.username || '已登录用户') : '访客');
    $('#homeUserMeta') && ($('#homeUserMeta').textContent = loggedIn ? `账号 ID：${user?.id || '--'} · ${user?.is_admin ? '已登录用户' : '普通用户'}` : '在数据看板直接登录/注册，统一管理 Token、Key 与渠道模型。');
    $('#homeBalance') && ($('#homeBalance').textContent = wallet ? fmtTokens(wallet.balance_tokens) : '--');
    $('#homeKeyCount') && ($('#homeKeyCount').textContent = loggedIn ? String(keys.length) : '--');
    $('#homeUsageCount') && ($('#homeUsageCount').textContent = loggedIn ? String(usage.length) : '--');
    $('#homeSubscription') && ($('#homeSubscription').textContent = activeSub ? (activeSub.title || activeSub.package_id || '有效订阅') : '暂无订阅');
    $('#homeBaseUrl') && ($('#homeBaseUrl').textContent = data.base_url || '--');

    const authCard = $('#homeAuthCard');
    const loggedCard = $('#homeLoggedCard');
    if (authCard) authCard.hidden = loggedIn;
    if (loggedCard) loggedCard.hidden = !loggedIn;

    const keyList = $('#homeRecentKeys');
    if (keyList) {
      keyList.innerHTML = loggedIn && keys.length
        ? keys.slice(0, 3).map(key => `<div><span>${esc(key.name || 'API Key')}</span><strong>${esc(key.key_prefix || key.prefix || 'sk-***')}...</strong><em>${esc(key.status || 'active')}</em></div>`).join('')
        : '<p class="aif-empty">还没有 API Key。点击“创建密钥”后，完整密钥只显示一次，请立即保存。</p>';
    }

    renderPlans();
  }

  function renderAccount() {
    const wallet = state.overview?.wallet;
    const user = state.overview?.user;
    $('#tokenBalance') && ($('#tokenBalance').textContent = wallet ? fmtTokens(wallet.balance_tokens) : '未登录');
    $('#accountHint') && ($('#accountHint').textContent = user ? `${user.nickname || user.username} · 已关联本站账号` : '请先在数据看板注册/登录');
    renderHome();
  }

  async function loadOverview() {
    try {
      if (!state.overview) {
        window.AIF?.showSkeleton?.('#homePlanPreview', 3);
        window.AIF?.showSkeleton?.('#homeRecentKeys', 2);
      }
      state.overview = await request(API.overview);
      renderAccount();
      window.AIF?.clearBusy?.('#homePlanPreview');
      window.AIF?.clearBusy?.('#homeRecentKeys');
      return state.overview;
    } catch (error) {
      console.warn(error);
      state.overview = state.overview || { ok: false, logged_in: false, packages: [] };
      renderAccount();
      return null;
    }
  }

  function ensureLoggedIn(actionText = '操作') {
    if (state.overview?.logged_in) return true;
    switchView('home');
    switchHomeAuthTab('login');
    setAuthStatus(`请先登录后再${actionText}。`, 'error');
    toast('请先登录本站账号', 'error');
    return false;
  }

  async function createKey() {
    if (!ensureLoggedIn('创建 API Key')) return;
    try {
      const data = await request(API.createKey, { method: 'POST', body: JSON.stringify({ name: 'Gateway Token Key' }) });
      const secret = data?.data?.secret;
      if (secret) {
        state.apiKey = secret;
        localStorage.setItem('aif_ai_api_key', secret);
        const box = $('#lastKeyBox') || $('#consoleSecretBox');
        if (box) {
          box.hidden = false;
          box.textContent = `请立即复制保存，刷新后不再显示完整密钥：\n${secret}`;
        }
        toast('API Key 已创建并已保存到当前浏览器');
      }
      await loadOverview();
    } catch (error) {
      toast(error.message || '创建失败', 'error');
    }
  }

  async function buyPackage(packageId, paymentType = 'alipay') {
    if (!packageId) return;
    if (!ensureLoggedIn('处理套餐')) return;
    try {
      const data = await request(API.createOrder, { method: 'POST', body: JSON.stringify({ package_id: packageId, payment_type: paymentType }) });
      if (data.pay_url) {
        window.open(data.pay_url, '_blank', 'noopener,noreferrer');
        toast('订单已创建，请在新窗口完成支付');
      } else {
        toast(data.msg || '套餐已到账');
      }
      await loadOverview();
    } catch (error) {
      toast(error.message || '套餐处理失败', 'error');
    }
  }

  function claimTrial() {
    return buyPackage('trial_20k', 'free');
  }

  function addMessage(role, content) {
    state.messages.push({ role, content });
    const box = $('#chatMessages');
    const welcome = box?.querySelector('.aif-welcome');
    if (welcome) welcome.remove();
    const item = document.createElement('div');
    item.className = `aif-msg ${role === 'user' ? 'is-user' : 'is-assistant'}`;
    item.innerHTML = `<div class="aif-msg-bubble">${esc(content)}</div>`;
    box?.appendChild(item);
    if (box) box.scrollTop = box.scrollHeight;
    return item.querySelector('.aif-msg-bubble');
  }

  async function ensureApiKey() {
    if (state.apiKey) return state.apiKey;
    if (!ensureLoggedIn('使用对话功能')) return '';
    if (state.overview?.keys?.length) {
      toast('完整密钥只在创建时显示；需要在线调试请重新创建一个 Key。');
    }
    await createKey();
    return state.apiKey;
  }

  async function sendPrompt(prompt) {
    await ensureRegistry();
    const key = await ensureApiKey();
    if (!key) return;
    const model = $('#modelSelect')?.value || 'kimi::kimi-k2.6';
    const maxTokens = Number($('#maxTokensInput')?.value || 4096);
    const temperature = Number($('#temperatureInput')?.value || 0.7);
    const topP = Number($('#topPInput')?.value || 0.9);
    addMessage('user', prompt);
    const bubble = addMessage('assistant', '思考中...');

    const messages = state.messages.slice(-12).map(item => ({ role: item.role, content: item.content }));
    try {
      const data = await request(API.chat, {
        method: 'POST',
        headers: { Authorization: `Bearer ${key}` },
        body: JSON.stringify({ model, messages, temperature, top_p: topP, max_tokens: maxTokens, stream: false })
      });
      const content = data?.choices?.[0]?.message?.content || data?.choices?.[0]?.text || '服务没有返回文本。';
      state.messages[state.messages.length - 1].content = content;
      bubble.textContent = content;
      if (data?.usage?.balance_tokens !== undefined) {
        state.overview = state.overview || {};
        state.overview.wallet = { ...(state.overview.wallet || {}), balance_tokens: data.usage.balance_tokens };
        renderAccount();
      }
    } catch (error) {
      const message = error.message || '调用失败';
      state.messages[state.messages.length - 1].content = message;
      bubble.textContent = `调用失败：${message}`;
    }
  }

  function loadConsoleScript() {
    if (window.__aifConsoleLoaded) return Promise.resolve();
    if (state.consolePromise) return state.consolePromise;
    state.consolePromise = new Promise((resolve, reject) => {
      const script = document.createElement('script');
      script.src = 'assets/ai_console.js?v=20260506-newapi-gateway';
      script.defer = true;
      script.onload = () => { window.__aifConsoleLoaded = true; resolve(); };
      script.onerror = () => reject(new Error('控制台脚本加载失败'));
      document.body.appendChild(script);
    }).catch(error => {
      toast(error.message, 'error');
      throw error;
    });
    return state.consolePromise;
  }

  async function switchView(view = 'home', options = {}) {
    view = validViews.has(view) ? view : 'home';
    state.view = view;
    if (options.updateHistory !== false) updateViewUrl(view, !!options.replaceHistory);
    const layout = $('.aif-layout');
    const titleMap = {
      home: ['数据看板', 'AI 中转站运营总览'],
      chat: ['Playground', '多渠道模型调试'],
      models: ['渠道模型', '查看和选择已接入渠道'],
      console: ['令牌控制台', 'AI 中转站接入控制台']
    };

    $$('.aif-nav-item[data-view]').forEach(btn => btn.classList.toggle('is-active', btn.dataset.view === view));
    $$('.aif-mobile-bottom-nav [data-jump-view]').forEach(btn => btn.classList.toggle('is-active', btn.dataset.jumpView === view));
    $$('[data-view-panel]').forEach(panel => {
      const active = panel.dataset.viewPanel === view;
      panel.hidden = !active;
      panel.setAttribute('aria-hidden', active ? 'false' : 'true');
      panel.style.display = active ? '' : 'none';
    });

    if (layout) layout.classList.toggle('aif-wide-view', view !== 'chat');
    const [title, subtitle] = titleMap[view] || titleMap.home;
    $('.aif-title span') && ($('.aif-title span').textContent = title);
    $('.aif-title strong') && ($('.aif-title strong').textContent = subtitle);

    const providerStrip = $('#providerStrip');
    if (providerStrip) providerStrip.hidden = !['chat', 'models'].includes(view);

    if (view === 'chat' || view === 'models') await ensureRegistry();
    if (view === 'models') renderModels();
    if (view === 'home') renderHome();
    if (view === 'console') {
      await loadConsoleScript();
      window.dispatchEvent(new CustomEvent('aif:console-visible'));
    }
    if (typeof window.aifSetMobileNavOpen === 'function') {
      window.aifSetMobileNavOpen(false);
    } else {
      document.body.classList.remove('aif-nav-open');
    }
  }

  async function handleLogin(event) {
    event.preventDefault();
    const form = event.currentTarget;
    const submit = form.querySelector('button[type="submit"]');
    try {
      submit.disabled = true;
      setAuthStatus('正在登录...');
      const data = await authRequest('user_login', formPayload(form));
      setAuthStatus(data.msg || '登录成功，已回到个人主页。', 'ok');
      if (typeof form.reset === 'function') form.reset();
      await loadOverview();
      switchView('home');
    } catch (error) {
      setAuthStatus(error.message || '登录失败', 'error');
      refreshCaptcha('login');
    } finally {
      submit.disabled = false;
    }
  }

  async function handleRegister(event) {
    event.preventDefault();
    const form = event.currentTarget;
    const submit = form.querySelector('button[type="submit"]');
    try {
      submit.disabled = true;
      setAuthStatus('正在创建账号...');
      const data = await authRequest('register', formPayload(form));
      setAuthStatus(data.msg || '注册成功，请登录。', 'ok');
      if (typeof form.reset === 'function') form.reset();
      switchHomeAuthTab('login');
    } catch (error) {
      setAuthStatus(error.message || '注册失败', 'error');
      refreshCaptcha('register');
    } finally {
      submit.disabled = false;
    }
  }

  async function handleSendEmailCode() {
    const form = $('#homeRegisterForm');
    const btn = $('#homeSendEmailCodeBtn');
    if (!form || !btn) return;
    const payload = formPayload(form);
    if (!payload.email || !payload.image_captcha) {
      setAuthStatus('请先填写邮箱和图片验证码，再获取邮箱验证码。', 'error');
      return;
    }
    try {
      btn.disabled = true;
      setAuthStatus('正在发送邮箱验证码...');
      const data = await authRequest('send_email_code', { email: payload.email, image_captcha: payload.image_captcha });
      setAuthStatus(data.msg || '邮箱验证码已发送，请查收。', 'ok');
      form.elements.image_captcha.value = '';
      refreshCaptcha('register');
      let rest = 60;
      const original = btn.innerHTML;
      btn.textContent = `${rest}s 后重发`;
      const timer = setInterval(() => {
        rest -= 1;
        btn.textContent = rest > 0 ? `${rest}s 后重发` : '获取验证码';
        if (rest <= 0) {
          clearInterval(timer);
          btn.disabled = false;
          btn.innerHTML = original;
        }
      }, 1000);
    } catch (error) {
      setAuthStatus(error.message || '验证码发送失败', 'error');
      refreshCaptcha('register');
      btn.disabled = false;
      btn.innerHTML = '<i class="fa fa-envelope-o"></i> 获取验证码';
    }
  }

  async function handleLogout() {
    try {
      setAuthStatus('正在退出...');
      const data = await authRequest('logout');
      localStorage.removeItem('aif_ai_api_key');
      state.apiKey = '';
      state.overview = { ...(state.overview || {}), logged_in: false, user: null, wallet: null, keys: [], usage: [], subscriptions: [] };
      renderAccount();
      setAuthStatus(data.msg || '已退出。', 'ok');
      refreshCaptcha('login');
    } catch (error) {
      setAuthStatus(error.message || '退出失败', 'error');
    }
  }

  function bind() {
    const mobileMenuBtn = $('#mobileMenuBtn');
    const mobileBackdrop = $('#mobileBackdrop');
    const sidebar = document.querySelector('.aif-sidebar');
    const isMobileNav = () => window.matchMedia('(max-width: 820px)').matches;
    const setMobileNavOpen = open => {
      open = !!open && isMobileNav();
      document.body.classList.toggle('aif-nav-open', open);
      if (mobileMenuBtn) mobileMenuBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
      if (mobileMenuBtn) mobileMenuBtn.setAttribute('aria-label', open ? '关闭导航' : '打开导航');
      if (mobileBackdrop) mobileBackdrop.setAttribute('aria-hidden', open ? 'false' : 'true');
      if (sidebar) sidebar.setAttribute('aria-hidden', open ? 'false' : 'true');
      document.documentElement.style.overflow = open ? 'hidden' : '';
      document.body.style.overflow = open ? 'hidden' : '';
    };
    window.aifSetMobileNavOpen = setMobileNavOpen;
    mobileMenuBtn?.setAttribute('aria-expanded', 'false');
    mobileMenuBtn?.setAttribute('aria-controls', 'siteSidebar');
    mobileBackdrop?.setAttribute('aria-hidden', 'true');
    if (sidebar) {
      sidebar.id = sidebar.id || 'siteSidebar';
      sidebar.setAttribute('aria-hidden', isMobileNav() ? 'true' : 'false');
    }
    mobileMenuBtn?.addEventListener('click', event => {
      event.preventDefault();
      setMobileNavOpen(!document.body.classList.contains('aif-nav-open'));
    });
    mobileBackdrop?.addEventListener('click', () => setMobileNavOpen(false));
    document.addEventListener('keydown', event => {
      if (event.key === 'Escape') setMobileNavOpen(false);
    });
    window.addEventListener('resize', () => {
      if (!isMobileNav()) setMobileNavOpen(false);
    });

    let touchStartX = 0;
    let touchStartY = 0;
    document.addEventListener('touchstart', event => {
      const touch = event.touches?.[0];
      if (!touch || !isMobileNav()) return;
      touchStartX = touch.clientX;
      touchStartY = touch.clientY;
    }, { passive: true });
    document.addEventListener('touchend', event => {
      const touch = event.changedTouches?.[0];
      if (!touch || !isMobileNav()) return;
      const dx = touch.clientX - touchStartX;
      const dy = Math.abs(touch.clientY - touchStartY);
      if (touchStartX < 24 && dx > 70 && dy < 60) setMobileNavOpen(true);
      if (document.body.classList.contains('aif-nav-open') && dx < -70 && dy < 70) setMobileNavOpen(false);
    }, { passive: true });

    $$('.aif-nav-item[data-view]').forEach(btn => {
      btn.type = 'button';
      btn.addEventListener('click', event => {
        event.preventDefault();
        switchView(btn.dataset.view || 'home');
        if (isMobileNav()) setMobileNavOpen(false);
      });
    });

    $$('#providerStrip button').forEach(btn => btn.addEventListener('click', () => {
      $$('#providerStrip button').forEach(item => item.classList.remove('is-active'));
      btn.classList.add('is-active');
      state.selectedProvider = btn.dataset.provider || 'all';
      renderModels();
    }));

    $('#modelSearchInput')?.addEventListener('input', debounce(renderModels));
    $('#syncModelsBtn')?.addEventListener('click', async () => { await loadRegistry(true); toast('服务列表已同步'); });
    $('#createKeyBtn')?.addEventListener('click', createKey);
    $('#homeCreateKeyBtn')?.addEventListener('click', createKey);
    $('#claimTrialBtn')?.addEventListener('click', claimTrial);
    $('#homeClaimTrialBtn')?.addEventListener('click', claimTrial);
    $('#openConsoleBtn')?.addEventListener('click', () => switchView('console'));
    $('#accountBtn')?.addEventListener('click', () => switchView('home'));
    $('#homeLoginForm')?.addEventListener('submit', handleLogin);
    $('#homeRegisterForm')?.addEventListener('submit', handleRegister);
    $('#homeSendEmailCodeBtn')?.addEventListener('click', handleSendEmailCode);
    $('#homeLogoutBtn')?.addEventListener('click', handleLogout);

    $$('[data-home-auth-tab]').forEach(btn => btn.addEventListener('click', () => switchHomeAuthTab(btn.dataset.homeAuthTab)));
    $$('[data-home-refresh-captcha]').forEach(btn => btn.addEventListener('click', () => refreshCaptcha(btn.dataset.homeRefreshCaptcha)));

    $('#clearChatBtn')?.addEventListener('click', () => {
      state.messages = [];
      const box = $('#chatMessages');
      if (box) box.innerHTML = '<div class="aif-welcome"><div class="aif-welcome-orb"><i class="fa fa-magic"></i></div><h1>已清空上下文</h1><p>可以开始新的对话。</p></div>';
    });

    ['maxTokensInput', 'temperatureInput', 'topPInput'].forEach(id => {
      const input = $('#' + id);
      const output = $('#' + id.replace('Input', 'Output'));
      input?.addEventListener('input', () => { if (output) output.textContent = input.value; });
    });

    document.addEventListener('click', event => {
      const jumpBtn = event.target.closest('[data-jump-view]');
      if (jumpBtn) {
        event.preventDefault();
        switchView(jumpBtn.dataset.jumpView || 'home');
        return;
      }

      const buyBtn = event.target.closest('[data-buy-package]');
      // 【修改｜低风险】控制台内套餐按钮由 ai_console.js 处理，避免主页监听器重复创建订单。
      if (buyBtn && !buyBtn.closest('[data-view-panel="console"]')) {
        const free = /trial|free/i.test(buyBtn.dataset.buyPackage || '');
        buyPackage(buyBtn.dataset.buyPackage, free ? 'free' : 'alipay');
        return;
      }

      const promptBtn = event.target.closest('[data-prompt]');
      if (promptBtn) $('#promptInput') && ($('#promptInput').value = promptBtn.dataset.prompt || '');

      const copyTextBtn = event.target.closest('[data-copy-text]');
      if (copyTextBtn) {
        const value = copyTextBtn.dataset.copyText || '';
        if (navigator.clipboard?.writeText) navigator.clipboard.writeText(value).then(() => toast('已复制模型 ID')).catch(() => toast(value));
        else toast(value);
        return;
      }

      const useBtn = event.target.closest('[data-use-model]');
      if (useBtn) {
        const value = useBtn.dataset.useModel;
        const select = $('#modelSelect');
        if (select && !Array.from(select.options).some(option => option.value === value)) {
          const option = document.createElement('option');
          option.value = value;
          option.textContent = value;
          select.appendChild(option);
        }
        if (select) select.value = value;
        switchView('chat');
        toast('已切换服务：' + value);
      }
    });

    window.addEventListener('popstate', () => switchView(viewFromLocation(), { updateHistory: false }));

    $('#chatForm')?.addEventListener('submit', async event => {
      event.preventDefault();
      const input = $('#promptInput');
      const prompt = (input?.value || '').trim();
      if (!prompt) return;
      input.value = '';
      await sendPrompt(prompt);
    });
  }

  function boot() {
    bind();
    switchHomeAuthTab('login');
    switchView(viewFromLocation(), { updateHistory: false, replaceHistory: true });
    loadOverview();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot, { once: true });
  } else {
    boot();
  }
})();
