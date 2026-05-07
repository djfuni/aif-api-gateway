(() => {
  'use strict';

  const $ = (selector, root = document) => root.querySelector(selector);
  const $$ = (selector, root = document) => Array.from(root.querySelectorAll(selector));
  const statusEl = $('#authStatus');
  let activeTab = 'login';

  function setStatus(message, isError = false) {
    if (!statusEl) return;
    statusEl.textContent = message || '';
    statusEl.classList.toggle('is-error', !!isError);
  }

  function captchaUrl() {
    return `auth.php?action=captcha_svg&_=${Date.now()}${Math.random().toString(36).slice(2)}`;
  }

  function refreshCaptcha(type = activeTab) {
    const img = type === 'register' ? $('#registerCaptcha') : $('#loginCaptcha');
    if (img) img.src = captchaUrl();
  }

  async function api(action, payload = {}) {
    const form = new FormData();
    Object.entries(payload).forEach(([key, value]) => form.append(key, value == null ? '' : String(value)));
    const response = await fetch(`auth.php?action=${encodeURIComponent(action)}`, {
      method: 'POST',
      body: form,
      credentials: 'same-origin',
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    });
    const text = await response.text();
    let data;
    try { data = text ? JSON.parse(text) : {}; } catch (error) { data = { ok: false, msg: text || '接口返回异常' }; }
    if (!response.ok || data.ok === false || data.status === 'error') {
      throw new Error(data.msg || data.message || `请求失败：${response.status}`);
    }
    return data;
  }

  function formPayload(form) {
    return Object.fromEntries(new FormData(form).entries());
  }

  function switchTab(tab) {
    activeTab = tab === 'register' ? 'register' : 'login';
    $$('[data-auth-tab]').forEach(btn => btn.classList.toggle('is-active', btn.dataset.authTab === activeTab));
    $('#loginForm')?.classList.toggle('is-active', activeTab === 'login');
    $('#registerForm')?.classList.toggle('is-active', activeTab === 'register');
    setStatus('');
    refreshCaptcha(activeTab);
  }

  function renderUser(user) {
    const box = $('#currentUser');
    if (!box) return;
    if (!user) {
      box.hidden = true;
      return;
    }
    box.hidden = false;
    $('#currentUserName').textContent = user.nickname || user.username || '已登录账号';
    const role = user.is_admin ? '管理员' : (user.role || 'user');
    const points = Number.isFinite(Number(user.points)) ? ` · ${Number(user.points)} 积分` : '';
    $('#currentUserMeta').textContent = `${role}${points}`;
  }

  async function loadMe() {
    try {
      const data = await api('me');
      renderUser(data.user || null);
      if (data.user) setStatus('当前已登录本站账号，可前往控制台领取额度或创建 API Key。');
    } catch (error) {
      renderUser(null);
    }
  }

  async function handleLogin(event) {
    event.preventDefault();
    const form = event.currentTarget;
    if (!form) return;
    const submit = form.querySelector('button[type="submit"]');
    try {
      if (submit) submit.disabled = true;
      setStatus('正在登录...');
      const data = await api('user_login', formPayload(form));
      setStatus(data.msg || '登录成功');
      renderUser(data.user || null);
      if (typeof form.reset === 'function') form.reset();
      // 【修改｜体验优化｜风险等级：低】登录成功后回到新的个人主页，不再把用户带到独立控制台/登录流程。
      setTimeout(() => { window.location.href = 'index.html'; }, 650);
    } catch (error) {
      setStatus(error.message || '登录失败', true);
      refreshCaptcha('login');
    } finally {
      if (submit) submit.disabled = false;
    }
  }

  async function handleSendEmailCode() {
    const form = $('#registerForm');
    const btn = $('#sendEmailCodeBtn');
    if (!form || !btn) return;
    const payload = formPayload(form);
    if (!payload.email || !payload.image_captcha) {
      setStatus('请先填写邮箱和图片验证码，再获取邮箱验证码。', true);
      return;
    }
    try {
      btn.disabled = true;
      setStatus('正在发送邮箱验证码...');
      const data = await api('send_email_code', { email: payload.email, image_captcha: payload.image_captcha });
      setStatus(data.msg || '邮箱验证码已发送，请查收。');
      form.elements.image_captcha.value = '';
      refreshCaptcha('register');
      let rest = 60;
      btn.textContent = `${rest}s 后重发`;
      const timer = setInterval(() => {
        rest -= 1;
        btn.textContent = rest > 0 ? `${rest}s 后重发` : '获取邮箱验证码';
        if (rest <= 0) { clearInterval(timer); btn.disabled = false; btn.innerHTML = '<i class="fa fa-envelope-o"></i> 获取邮箱验证码'; }
      }, 1000);
    } catch (error) {
      setStatus(error.message || '邮箱验证码发送失败', true);
      refreshCaptcha('register');
      btn.disabled = false;
      btn.innerHTML = '<i class="fa fa-envelope-o"></i> 获取邮箱验证码';
    }
  }

  async function handleRegister(event) {
    event.preventDefault();
    const form = event.currentTarget;
    if (!form) return;
    const submit = form.querySelector('button[type="submit"]');
    try {
      if (submit) submit.disabled = true;
      setStatus('正在创建本站账号...');
      const data = await api('register', formPayload(form));
      setStatus(data.msg || '注册成功，请登录。');
      if (typeof form.reset === 'function') form.reset();
      switchTab('login');
    } catch (error) {
      setStatus(error.message || '注册失败', true);
      refreshCaptcha('register');
    } finally {
      if (submit) submit.disabled = false;
    }
  }

  async function handleLogout() {
    try {
      setStatus('正在退出...');
      const data = await api('logout');
      renderUser(null);
      setStatus(data.msg || '已退出');
      refreshCaptcha(activeTab);
    } catch (error) {
      setStatus(error.message || '退出失败', true);
    }
  }

  document.addEventListener('DOMContentLoaded', () => {
    $$('[data-auth-tab]').forEach(btn => btn.addEventListener('click', () => switchTab(btn.dataset.authTab)));
    $$('[data-refresh-captcha]').forEach(btn => btn.addEventListener('click', () => refreshCaptcha(btn.dataset.refreshCaptcha)));
    $('#loginForm')?.addEventListener('submit', handleLogin);
    $('#registerForm')?.addEventListener('submit', handleRegister);
    $('#sendEmailCodeBtn')?.addEventListener('click', handleSendEmailCode);
    $('#logoutBtn')?.addEventListener('click', handleLogout);
    refreshCaptcha('login');
    loadMe();
  });
})();
