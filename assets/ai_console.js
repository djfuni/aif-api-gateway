(() => {
  'use strict';
  window.__aifConsoleLoaded = true;

  const $ = selector => document.querySelector(selector);
  const $$ = selector => Array.from(document.querySelectorAll(selector));
  const API = {
    overview: 'ai_api_console_api.php?action=overview',
    createKey: 'ai_api_console_api.php?action=generate_key',
    createOrder: 'ai_api_console_api.php?action=create_order',
    redeemCode: 'ai_api_console_api.php?action=redeem_code'
  };

  const state = {
    overview: null,
    selectedModelId: '',
    codeTab: 'curl',
    lastCode: '',
    playground: {
      apiKey: safeStorageGet('aif_playground_api_key')
    }
  };

  const esc = value => String(value ?? '').replace(/[&<>"']/g, ch => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
  }[ch]));

  function safeStorageGet(key) {
    try { return window.localStorage?.getItem(key) || ''; } catch { return ''; }
  }

  function safeStorageSet(key, value) {
    try { window.localStorage?.setItem(key, value); } catch {}
  }

  const fmt = value => Number(value || 0).toLocaleString('zh-CN');
  const compact = value => {
    const n = Number(value || 0);
    if (n >= 100000000) return (n / 100000000).toFixed(1).replace(/\.0$/, '') + '亿';
    if (n >= 10000) return (n / 10000).toFixed(1).replace(/\.0$/, '') + '万';
    return fmt(n);
  };

  async function request(url, options = {}) {
    const res = await fetch(url, { credentials: 'same-origin', headers: { 'Content-Type': 'application/json' }, ...options });
    const text = await res.text();
    let data = {};
    try { data = text ? JSON.parse(text) : {}; } catch { data = { ok: false, msg: text }; }
    if (!res.ok) throw new Error(data.msg || data?.error?.message || `HTTP ${res.status}`);
    return data;
  }

  function toast(message, type = 'default') {
    if (window.AIF?.showToast) return window.AIF.showToast(message, type === 'default' ? 'info' : type);
    console.log('[toast]', type, message);
  }

  function copyText(text, successText = '已复制') {
    const value = String(text || '').trim();
    if (!value) return toast('暂无可复制内容', 'error');
    if (navigator.clipboard?.writeText) {
      navigator.clipboard.writeText(value).then(() => toast(successText)).catch(() => fallbackCopy(value, successText));
    } else {
      fallbackCopy(value, successText);
    }
  }

  function fallbackCopy(text, successText) {
    const ta = document.createElement('textarea');
    ta.value = text;
    ta.setAttribute('readonly', '');
    Object.assign(ta.style, { position: 'fixed', left: '-999px', top: '0' });
    document.body.appendChild(ta);
    ta.select();
    try { document.execCommand('copy'); toast(successText); } catch { toast('复制失败，请手动复制', 'error'); }
    ta.remove();
  }

  function models() {
    return Array.isArray(state.overview?.models) ? state.overview.models : [];
  }

  function selectedModel() {
    const list = models();
    if (!list.length) return null;
    return list.find(item => item.id === state.selectedModelId) || list[0];
  }

  function baseUrl() {
    return String(state.overview?.base_url || '').replace(/\/$/, '');
  }

  function endpoint() {
    return `${baseUrl()}/chat/completions`;
  }

  function modelTags(model) {
    const tags = [];
    if (model?.type) tags.push(model.type === 'reasoning' ? '推理' : '对话');
    if (model?.provider) tags.push(String(model.provider).replace(/::$/, ''));
    if (model?.is_free) tags.push('免额度');
    if (model?.supports_thinking) tags.push('思考');
    if (model?.supports_image_input) tags.push('图像输入');
    if (model?.stream_supported) tags.push('流式');
    return tags.slice(0, 5);
  }

  function primaryKey() {
    const keys = Array.isArray(state.overview?.keys) ? state.overview.keys : [];
    return keys.find(k => (k.status || 'active') === 'active') || keys[0] || null;
  }

  function keyPlaceholder() {
    const key = primaryKey();
    if (key?.key_prefix) return `${key.key_prefix}...（请替换为创建时保存的完整密钥）`;
    return 'sk-kd-请先在下方创建 令牌 Key';
  }

  function currentPlanKind() {
    // 【修改｜低风险】控制台已内嵌主页，仅读取控制台区域的套餐 Tab，避免被主页其它 Tab 干扰。
    return document.querySelector('[data-view-panel="console"] .aif-plan-tabs button.is-active, .aif-console-page .aif-plan-tabs button.is-active')?.dataset.consolePlanKind || 'subscription';
  }

  function formatPrice(pkg) {
    const price = Number(pkg.price || 0);
    if (price <= 0) return '免费';
    return `¥${price}${pkg.kind === 'subscription' ? '/月' : ''}`;
  }

  function packageFeatures(pkg) {
    const features = Array.isArray(pkg.features) ? pkg.features : [];
    if (features.length) return features;
    if (pkg.kind === 'subscription') return [`${compact(pkg.tokens)} 额度 / ${pkg.period_days || 30} 天`, '购买后立即到账'];
    if (pkg.kind === 'trial') return [`${compact(pkg.tokens)} 额度`, '每个账号限领一次'];
    return [`${compact(pkg.tokens)} 额度`, '余额长期可用'];
  }

  function planCard(pkg) {
    return `<article class="aif-plan-card${pkg.recommended ? ' is-recommended' : ''}">
      <div class="aif-plan-badge">${esc(pkg.badge || (pkg.kind === 'subscription' ? '月度订阅' : '额度包'))}</div>
      <h3>${esc(pkg.title || pkg.id)}</h3>
      <p>${esc(pkg.description || '')}</p>
      <div class="aif-plan-price">${esc(formatPrice(pkg))}</div>
      <div class="aif-plan-tokens">${esc(compact(pkg.tokens || 0))} 额度${pkg.period_days ? ` · ${pkg.period_days} 天` : ''}</div>
      <ul>${packageFeatures(pkg).map(feature => `<li>${esc(feature)}</li>`).join('')}</ul>
      <button type="button" data-buy-package="${esc(pkg.id)}">${Number(pkg.price || 0) <= 0 ? '立即领取' : '选择套餐'}</button>
    </article>`;
  }

  function renderPlans() {
    const box = $('#consolePlans');
    if (!box) return;
    const packages = Array.isArray(state.overview?.packages) ? state.overview.packages : [];
    const kind = currentPlanKind();
    const rows = packages.filter(pkg => (pkg.kind || 'topup') === kind);
    box.innerHTML = rows.map(planCard).join('') || '<p class="aif-empty">暂无该类型套餐。</p>';
  }

  function renderSubscriptions() {
    const box = $('#consoleSubscriptions');
    if (!box) return;
    const rows = Array.isArray(state.overview?.subscriptions) ? state.overview.subscriptions : [];
    box.innerHTML = rows.map(sub => `<div class="aif-list-row">
      <div><b>${esc(sub.title || sub.package_id || '订阅记录')}</b><br><small>${esc(compact(sub.tokens || 0))} 额度 · ${esc(sub.started_at || '')} 至 ${esc(sub.active_until || '')}</small></div>
      <span class="${sub.is_active ? 'is-ok' : ''}">${sub.is_active ? '生效中' : '已过期'}</span>
    </div>`).join('') || '<p class="aif-empty">暂无订阅记录。</p>';
  }

  function renderKeys() {
    const box = $('#consoleKeys');
    if (!box) return;
    const rows = Array.isArray(state.overview?.keys) ? state.overview.keys : [];
    box.innerHTML = rows.map(key => `<div class="aif-list-row">
      <div><b>${esc(key.name || '令牌 Key')}</b><br><small>${esc(key.key_prefix || '')} · ${esc(key.status || 'active')} · ${esc(key.created_at || '')}</small></div>
      <span>${esc(key.last_used_at || '未调用')}</span>
    </div>`).join('') || '<p class="aif-empty">暂无 令牌 Key。请先点击“创建 令牌 Key”。</p>';
  }


  function renderRedeemRecords() {
    const box = $('#consoleRedeemRecords');
    if (!box) return;
    const rows = Array.isArray(state.overview?.redeem_records) ? state.overview.redeem_records : [];
    box.innerHTML = rows.map(row => `<div class="aif-list-row">
      <div><b>${esc(row.title || '兑换码到账')}</b><br><small>${esc(row.code_preview || '')} · ${esc(row.created_at || '')}</small></div>
      <span class="is-ok">+${esc(compact(row.tokens || 0))}</span>
    </div>`).join('') || '<p class="aif-empty">暂无兑换记录。</p>';
  }

  function filteredModels() {
    const query = ($('#consoleModelSearch')?.value || '').trim().toLowerCase();
    const type = $('#consoleTypeFilter')?.value || 'all';
    const feature = $('#consoleFeatureFilter')?.value || 'all';
    return models().filter(model => {
      const text = `${model.id || ''} ${model.label || ''} ${model.provider || ''} ${model.model_name || ''}`.toLowerCase();
      if (query && !text.includes(query)) return false;
      if (type === 'chat' && model.type !== 'chat') return false;
      if (type === 'reasoning' && model.type !== 'reasoning') return false;
      if (type === 'vision' && !model.supports_image_input) return false;
      if (type === 'zero_token' && !model.is_free) return false;
      if (type === 'openrouter_free') {
        const provider = String(model.provider || '').toLowerCase();
        const id = String(model.id || '').toLowerCase();
        if (!model.is_free || (!provider.includes('openrouter') && !id.startsWith('openrouter::'))) return false;
      }
      if (type === 'mimo') {
        const provider = String(model.provider || '').toLowerCase();
        const id = String(model.id || '').toLowerCase();
        if (id.startsWith('mimo2::') || provider.includes('key 2') || provider.includes('二号')) return false;
        if (!provider.includes('mimo') && !provider.includes('小米') && !id.startsWith('mimo::')) return false;
      }
      if (type === 'mimo2') {
        const provider = String(model.provider || '').toLowerCase();
        const id = String(model.id || '').toLowerCase();
        if (!id.startsWith('mimo2::') && !provider.includes('key 2') && !provider.includes('二号')) return false;
      }
      if (feature === 'stream' && !model.stream_supported) return false;
      if (feature === 'thinking' && !model.supports_thinking) return false;
      return true;
    });
  }

  function renderModelList() {
    const list = filteredModels();
    const box = $('#consoleModelList');
    const count = $('#consoleModelCount');
    if (count) count.textContent = `${list.length} / ${models().length || 0}`;
    if (!box) return;
    if (!list.length) {
      box.innerHTML = '<p class="aif-empty">没有匹配的模型，请调整搜索或筛选条件。</p>';
      return;
    }
    box.innerHTML = list.map(model => {
      const active = (selectedModel()?.id || '') === model.id;
      const tags = modelTags(model).slice(0, 2).map(tag => `<em>${esc(tag)}</em>`).join('');
      return `<button type="button" class="aif-model-item${active ? ' is-active' : ''}" data-select-model="${esc(model.id)}">
        <span class="aif-model-glyph"><i class="fa fa-cubes"></i></span>
        <span class="aif-model-copy">
          <b>${esc(model.label || model.id)}</b>
          <small>${esc(model.id)}</small>
          <span>${tags}</span>
          <u>${model.is_free ? '免额度' : `倍率 ${esc(model.token_multiplier || 1)}x`} · ${model.stream_supported ? '支持流式' : '非流式'}</u>
        </span>
        <span class="aif-model-state"><i></i>运行中</span>
      </button>`;
    }).join('');
  }

  function renderSummary() {
    const model = selectedModel();
    if (!model) return;
    $('#consoleSelectedModelTitle').textContent = model.label || model.id;
    $('#consoleSelectedModelSub').textContent = `调用时在请求体 model 字段填写：${model.id}`;
    $('#consoleModelIconLetter').textContent = String(model.label || model.id || 'M').slice(0, 2).toUpperCase();
    $('#consoleModelTags').innerHTML = modelTags(model).map(tag => `<span>${esc(tag)}</span>`).join('');
    $('#consoleSummaryMeta').innerHTML = [
      ['模型 ID', model.id, 'copy-model'],
      ['底层名称', model.model_name || model.id, 'copy-upstream'],
      ['费用标记', model.is_free ? (model.price_label || '免额度') : `${model.token_multiplier || 1}x`, ''],
      ['上下文/输出', `${model.context_length ? fmt(model.context_length) + ' ctx / ' : ''}${model.max_tokens || 4096} out`, ''],
      ['状态', '运行中', 'ok']
    ].map(([label, value, mode]) => `<div class="${mode === 'ok' ? 'is-ok' : ''}"><span>${esc(label)}</span><strong>${esc(value)}</strong>${mode.startsWith('copy') ? `<button type="button" data-copy="${esc(value)}"><i class="fa fa-copy"></i></button>` : ''}</div>`).join('');
  }

  function guideRows() {
    const model = selectedModel();
    const key = primaryKey();
    const provider = model?.provider || '默认网关';
    const providerText = String(provider).toLowerCase();
    const modelIdText = String(model?.id || '').toLowerCase();
    const isOpenRouter = providerText.includes('openrouter') || modelIdText.startsWith('openrouter::');
    const isMiMo = providerText.includes('mimo') || String(provider).includes('小米') || modelIdText.startsWith('mimo::') || modelIdText.startsWith('mimo2::');
    const isMiMo2 = modelIdText.startsWith('mimo2::') || providerText.includes('key 2') || providerText.includes('二号');
    const providerHelp = isOpenRouter ? '服务端使用 OpenRouter Key 转发，用户请求不暴露上游凭据' : (isMiMo ? (isMiMo2 ? '服务端使用小米 MiMo 二号 Key 转发，与 MiMo Key 1 同时存在、互不覆盖' : '服务端使用小米 MiMo 一号 Key 转发，前台只需要本站 令牌 Key') : '由本站网关统一路由到对应模型服务商');
    return [
      { icon: 'cloud', label: '上游平台', value: provider, help: providerHelp, copy: '' },
      { icon: 'link', label: 'Base URL（兼容格式）', value: baseUrl() || '--', help: '填到兼容 SDK 的 base_url / baseURL', copy: baseUrl() },
      { icon: 'paper-plane-o', label: 'Endpoint', value: endpoint(), help: 'Chat Completions 请求地址', copy: endpoint() },
      { icon: 'key', label: '令牌 Key', value: key ? `${key.key_prefix}...` : '未创建，请点击下方“创建 令牌 Key”', help: '请求头中使用：Authorization: Bearer {APIKey}', copy: '' },
      { icon: 'shield', label: '鉴权方式', value: 'Bearer 认证', help: '请求头：Authorization: Bearer 你的完整 令牌 Key', copy: 'Authorization: Bearer YOUR_API_KEY' },
      { icon: 'id-badge', label: 'Model 参数（model）', value: model?.id || '--', help: '必须在请求体的 model 字段中填写', copy: model?.id || '' },
      { icon: 'exchange', label: '请求方式', value: 'POST', help: '推荐使用 HTTPS POST 请求', copy: '' },
      { icon: 'file-code-o', label: 'Content-Type', value: 'application/json', help: '请求头中必须声明 JSON', copy: 'Content-Type: application/json' },
      { icon: 'ban', label: 'APPID / APISecret', value: '不需要填写', help: isOpenRouter ? 'OpenRouter 密钥只在服务端 data/ai_providers_private.php 中配置；前台只暴露本站 令牌 Key' : '本站网关已封装上游认证，只需要 令牌 Key + model', copy: '' },
      { icon: 'gift', label: '免额度扣费', value: model?.is_free ? '是' : '否', help: model?.is_free ? '该服务调用时记录真实用量，但 charged_tokens 为 0，不扣站内Token 余额' : '该服务会按倍率扣除站内额度', copy: '' }
    ];
  }

  function renderGuide() {
    const box = $('#consoleGuideRows');
    if (!box) return;
    box.innerHTML = guideRows().map(row => `<div class="aif-guide-row">
      <i class="fa fa-${esc(row.icon)}"></i>
      <div><span>${esc(row.label)}</span><strong>${esc(row.value)}</strong><small>${esc(row.help)}</small></div>
      ${row.copy ? `<button type="button" data-copy="${esc(row.copy)}"><i class="fa fa-copy"></i></button>` : ''}
    </div>`).join('');
  }

  function renderParams() {
    const model = selectedModel();
    const rows = [
      ['model', 'string', '是', model?.id || '模型 ID', '必须填写为当前服务的 modelId'],
      ['messages', 'array', '是', '[{"role":"user","content":"你好"}]', '对话消息列表，兼容 Chat Completions 格式'],
      ['stream', 'boolean', '否', String(!!model?.stream_supported), '是否流式返回；服务不支持时请传 false'],
      ['max_completion_tokens', 'integer', '否', String(Math.min(Number(model?.max_tokens || 4096), 1024)), '限制本次最大输出长度'],
      ['temperature', 'number', '否', '0.7', '控制随机性，数值越高越发散'],
      ['response_format', 'object', '否', '{"type":"json_object"}', '需要 JSON 输出时可配置，取决于上游支持情况']
    ];
    $('#consoleParamTable').innerHTML = rows.map(row => `<tr>
      <td><code>${esc(row[0])}</code></td><td>${esc(row[1])}</td><td>${row[2] === '是' ? '<b class="aif-required">是</b>' : '否'}</td><td>${esc(row[3])}</td><td>${esc(row[4])}</td>
    </tr>`).join('');
  }

  function buildExamples() {
    const model = selectedModel();
    const id = model?.id || 'your-model-id';
    const key = keyPlaceholder();
    const url = endpoint();
    const root = baseUrl();
    return {
      curl: `curl -X POST "${url}" \\
  -H "Authorization: Bearer ${key}" \\
  -H "Content-Type: application/json" \\
  -d '{\n    "model": "${id}",\n    "messages": [{"role": "user", "content": "你好，请用一句话介绍这个服务"}],\n    "stream": false,\n    "max_completion_tokens": 512\n  }'`,
      python: `from openai import OpenAI\n\nclient = OpenAI(\n    api_key="${key}",\n    base_url="${root}"\n)\n\nresp = client.chat.completions.create(\n    model="${id}",\n    messages=[{"role": "user", "content": "你好，请用一句话介绍这个服务"}],\n    max_completion_tokens=512,\n    stream=False\n)\n\nprint(resp.choices[0].message.content)`,
      javascript: `const response = await fetch("${url}", {\n  method: "POST",\n  headers: {\n    "Authorization": "Bearer ${key}",\n    "Content-Type": "application/json"\n  },\n  body: JSON.stringify({\n    model: "${id}",\n    messages: [{ role: "user", content: "你好，请用一句话介绍这个服务" }],\n    stream: false,\n    max_completion_tokens: 512\n  })\n});\n\nconst data = await response.json();\nconsole.log(data.choices?.[0]?.message?.content);`
    };
  }

  function renderExamples() {
    const examples = buildExamples();
    const code = examples[state.codeTab] || examples.curl;
    state.lastCode = code;
    $('#consoleDocs').textContent = code;
    $$('#consoleExampleTabs [data-code-tab]').forEach(btn => btn.classList.toggle('is-active', btn.dataset.codeTab === state.codeTab));
  }

  function playgroundValues() {
    return {
      apiKey: ($('#consolePlaygroundKey')?.value || '').trim(),
      prompt: ($('#consolePlaygroundPrompt')?.value || '').trim(),
      maxTokens: Math.max(1, Number($('#consolePlaygroundMaxTokens')?.value || 512) || 512),
      temperature: Math.min(2, Math.max(0, Number($('#consolePlaygroundTemperature')?.value || 0.7) || 0.7))
    };
  }

  function extractAssistantText(data) {
    const choice = data?.choices?.[0]?.message;
    if (!choice) return '';
    const content = choice.content;
    if (typeof content === 'string') return content;
    if (Array.isArray(content)) {
      return content.map(item => typeof item === 'string' ? item : (item?.text || item?.content || '')).join('\n').trim();
    }
    return '';
  }

  function buildPlaygroundCurl(maskKey = true) {
    const model = selectedModel();
    const vals = playgroundValues();
    const apiKey = vals.apiKey || 'YOUR_API_KEY';
    const keyText = maskKey && apiKey.length > 12 ? `${apiKey.slice(0, 8)}...${apiKey.slice(-4)}` : apiKey;
    return `curl -X POST "${endpoint()}" \
  -H "Authorization: Bearer ${keyText}" \
  -H "Content-Type: application/json" \
  -d '{
    "model": "${model?.id || 'your-model-id'}",
    "messages": [{"role": "user", "content": ${JSON.stringify(vals.prompt || '你好，请介绍一下这个模型')}}],
    "stream": false,
    "temperature": ${vals.temperature},
    "max_completion_tokens": ${vals.maxTokens}
  }'`;
  }

  function renderPlayground() {
    const keyInput = $('#consolePlaygroundKey');
    const meta = $('#consolePlaygroundMeta');
    if (keyInput && !keyInput.value && state.playground.apiKey) keyInput.value = state.playground.apiKey;
    const maxInput = $('#consolePlaygroundMaxTokens');
    const model = selectedModel();
    if (maxInput && (!maxInput.value || Number(maxInput.value) <= 0)) maxInput.value = Math.min(Number(model?.max_tokens || 1024), 1024);
    if (meta) meta.innerHTML = `<span>当前模型：${esc(model?.id || '--')}</span><span>Endpoint：${esc(endpoint())}</span>`;
  }

  async function runPlayground() {
    const vals = playgroundValues();
    const model = selectedModel();
    const output = $('#consolePlaygroundOutput');
    const meta = $('#consolePlaygroundMeta');
    const runBtn = $('#consoleRunBtn');
    if (!vals.apiKey) return toast('请先粘贴完整 令牌 Key', 'error');
    if (!vals.prompt) return toast('请输入测试 Prompt', 'error');
    state.playground.apiKey = vals.apiKey;
    localStorage.setItem('aif_playground_api_key', vals.apiKey);
    const started = performance.now();
    if (runBtn) runBtn.disabled = true;
    if (output) output.textContent = '请求发送中，请稍候...';
    if (meta) meta.innerHTML = `<span>当前模型：${esc(model?.id || '--')}</span><span>请求发送中...</span>`;
    try {
      const body = {
        model: model?.id || '',
        messages: [{ role: 'user', content: vals.prompt }],
        stream: false,
        temperature: vals.temperature,
        max_completion_tokens: vals.maxTokens
      };
      const data = await request(endpoint(), {
        method: 'POST',
        headers: { Authorization: `Bearer ${vals.apiKey}` },
        body: JSON.stringify(body)
      });
      const text = extractAssistantText(data) || JSON.stringify(data, null, 2);
      const elapsed = Math.round(performance.now() - started);
      if (output) output.textContent = text;
      if (meta) meta.innerHTML = `<span>当前模型：${esc(model?.id || '--')}</span><span>完成：${elapsed} ms · 输出 ${(text || '').length} 字符</span>`;
      toast('测试请求已完成');
    } catch (err) {
      if (output) output.textContent = err.message || '请求失败';
      if (meta) meta.innerHTML = `<span>当前模型：${esc(model?.id || '--')}</span><span>请求失败，请检查 令牌 Key、余额或模型权限</span>`;
      toast(err.message || '测试请求失败', 'error');
    } finally {
      if (runBtn) runBtn.disabled = false;
    }
  }

  function clearPlayground() {
    const prompt = $('#consolePlaygroundPrompt');
    const output = $('#consolePlaygroundOutput');
    if (prompt) prompt.value = '';
    if (output) output.textContent = '等待调试请求...';
    renderPlayground();
  }

  function copyPlaygroundCurl() {
    copyText(buildPlaygroundCurl(false), '当前 cURL 已复制');
  }

  function renderUsage() {
    const usage = Array.isArray(state.overview?.usage) ? state.overview.usage : [];
    const model = selectedModel();
    const modelUsage = usage.filter(row => !model?.id || row.model === model.id);
    const rows = modelUsage.length ? modelUsage : usage;
    const totalCalls = rows.length;
    const totalCharged = rows.reduce((sum, row) => sum + Number(row.charged_tokens || 0), 0);
    const totalTokens = rows.reduce((sum, row) => sum + Number(row.total_tokens || 0), 0);
    $('#consoleUsageStats').innerHTML = `<div><span>调用次数</span><strong>${fmt(totalCalls)}</strong><small>近 30 条记录</small></div>
      <div><span>消耗额度</span><strong>${compact(totalTokens)}</strong><small>服务返回用量</small></div>
      <div><span>扣费额度</span><strong>${compact(totalCharged)}</strong><small>免额度服务显示 0</small></div>`;

    const days = [];
    const now = new Date();
    for (let i = 6; i >= 0; i--) {
      const d = new Date(now);
      d.setDate(now.getDate() - i);
      const key = d.toISOString().slice(0, 10);
      days.push({ key, label: `${d.getMonth() + 1}/${d.getDate()}`, value: 0 });
    }
    rows.forEach(row => {
      const key = String(row.created_at || '').slice(0, 10);
      const item = days.find(day => day.key === key);
      if (item) item.value += Number(row.charged_tokens || row.total_tokens || 1);
    });
    const max = Math.max(...days.map(d => d.value), 1);
    $('#consoleUsageChart').innerHTML = days.map(day => {
      const height = Math.max(8, Math.round(day.value / max * 112));
      return `<div class="aif-usage-bar" title="${esc(day.label)}：${esc(day.value)}"><span style="height:${height}px"></span><em>${esc(day.label)}</em></div>`;
    }).join('');
  }

  function renderAccount() {
    const data = state.overview || {};
    $('#consoleAccount').textContent = data.logged_in ? (data.user?.nickname || data.user?.username || '已登录') : '未登录';
    $('#consoleAccountHint').textContent = data.logged_in ? '已关联本站账号，可创建密钥并调用服务。' : '请先在本站注册/登录，再刷新本页。';
    $('#consoleBalance').textContent = data.wallet ? fmt(data.wallet.balance_tokens) : '--';
    $('#consoleBaseUrl').textContent = data.base_url || '--';
  }

  function renderAll() {
    if (!state.selectedModelId && models().length) state.selectedModelId = models()[0].id;
    renderAccount();
    renderModelList();
    renderSummary();
    renderGuide();
    renderParams();
    renderExamples();
    renderPlayground();
    renderUsage();
    renderPlans();
    renderSubscriptions();
    renderKeys();
    renderRedeemRecords();
  }

  let loadingPromise = null;

  async function load() {
    const errorBox = $('#consoleError');
    try {
      if (!state.overview) {
        window.AIF?.showSkeleton?.('#consoleModelList', 5);
        window.AIF?.showSkeleton?.('#consolePlans', 3);
        window.AIF?.showSkeleton?.('#consoleKeys', 2);
      }
      if (errorBox) errorBox.hidden = true;
      const data = await request(API.overview);
      state.overview = data;
      renderAll();
      ['#consoleModelList','#consolePlans','#consoleKeys'].forEach(sel => window.AIF?.clearBusy?.(sel));
    } catch (err) {
      if (errorBox) {
        errorBox.hidden = false;
        errorBox.textContent = err.message || '控制台数据加载失败';
      }
      $('#consoleAccount').textContent = '未登录或接口不可用';
      $('#consoleAccountHint').textContent = err.message || '';
    }
  }

  async function createKey() {
    try {
      const data = await request(API.createKey, { method: 'POST', body: JSON.stringify({ name: 'Console Key' }) });
      if (data?.data?.secret) {
        const box = $('#consoleSecretBox');
        box.hidden = false;
        box.textContent = `请立即复制保存，刷新后不再显示完整密钥：\n${data.data.secret}`;
        copyText(data.data.secret, '完整 令牌 Key 已复制');
      }
      toast('令牌 Key 已创建');
      await load();
    } catch (err) {
      toast(err.message || '创建失败', 'error');
    }
  }

  async function buyPackage(packageId) {
    try {
      const data = await request(API.createOrder, { method: 'POST', body: JSON.stringify({ package_id: packageId, payment_type: 'alipay' }) });
      if (data.pay_url) {
        window.open(data.pay_url, '_blank', 'noopener,noreferrer');
        toast('订单已创建，请在新窗口完成支付');
      } else {
        toast(data.msg || '套餐已到账');
      }
      await load();
    } catch (err) {
      toast(err.message || '套餐处理失败', 'error');
    }
  }


  async function redeemCode() {
    const input = $('#consoleRedeemInput');
    const code = (input?.value || '').trim();
    if (!code) return toast('请输入兑换码', 'error');
    const btn = $('#consoleRedeemBtn');
    if (btn) btn.disabled = true;
    try {
      const data = await request(API.redeemCode, { method: 'POST', body: JSON.stringify({ code }) });
      toast(data.msg || '兑换成功');
      if (input) input.value = '';
      await load();
    } catch (err) {
      toast(err.message || '兑换失败', 'error');
    } finally {
      if (btn) btn.disabled = false;
    }
  }


  function activateConsoleSection(name, shouldScroll = false) {
    name = name || 'docs';
    const panels = $$('[data-console-section]');
    if (!panels.length) return;
    panels.forEach(panel => {
      const active = panel.dataset.consoleSection === name;
      panel.classList.toggle('is-active', active);
      panel.hidden = !active;
      panel.setAttribute('aria-hidden', active ? 'false' : 'true');
    });
    $$('[data-console-section-button]').forEach(btn => {
      const active = btn.dataset.consoleSectionButton === name;
      btn.classList.toggle('is-active', active);
      btn.setAttribute('aria-selected', active ? 'true' : 'false');
    });
    if (shouldScroll) {
      (document.querySelector(`[data-console-section="${name}"]`) || document.querySelector('.aif-console-section-nav'))?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
  }

  function bindEvents() {
    $('#consoleRefreshBtn')?.addEventListener('click', load);
    $('#consoleSidebarRefresh')?.addEventListener('click', load);
    $('#consoleCreateKeyBtn')?.addEventListener('click', createKey);
    $('#consoleClaimBtn')?.addEventListener('click', () => buyPackage('trial_20k'));
    $('#consoleModelSearch')?.addEventListener('input', renderModelList);
    $('#consoleTypeFilter')?.addEventListener('change', renderModelList);
    $('#consoleFeatureFilter')?.addEventListener('change', renderModelList);
    $$('[data-console-section-button]').forEach(btn => btn.addEventListener('click', () => activateConsoleSection(btn.dataset.consoleSectionButton || 'docs', true)));
    $('#consoleTryBtn')?.addEventListener('click', () => activateConsoleSection('playground', true));
    $('#consoleRunBtn')?.addEventListener('click', runPlayground);
    $('#consoleCopyCurlBtn')?.addEventListener('click', copyPlaygroundCurl);
    $('#consoleClearPlaygroundBtn')?.addEventListener('click', clearPlayground);
    $('#consolePlaygroundPrompt')?.addEventListener('keydown', event => {
      if ((event.ctrlKey || event.metaKey) && event.key === 'Enter') {
        event.preventDefault();
        runPlayground();
      }
    });
    $('#consolePlaygroundKey')?.addEventListener('change', event => {
      state.playground.apiKey = event.currentTarget.value.trim();
      safeStorageSet('aif_playground_api_key', state.playground.apiKey);
    });
    $('#consoleRedeemBtn')?.addEventListener('click', redeemCode);
    $('#consoleRedeemInput')?.addEventListener('keydown', event => {
      if (event.key === 'Enter') {
        event.preventDefault();
        redeemCode();
      }
    });

    $$('.aif-plan-tabs button[data-console-plan-kind]').forEach(btn => btn.addEventListener('click', () => {
      $$('.aif-plan-tabs button[data-console-plan-kind]').forEach(item => item.classList.remove('is-active'));
      btn.classList.add('is-active');
      renderPlans();
    }));

    document.addEventListener('click', event => {
      const modelBtn = event.target.closest('[data-select-model]');
      if (modelBtn) {
        state.selectedModelId = modelBtn.dataset.selectModel;
        renderAll();
      ['#consoleModelList','#consolePlans','#consoleKeys'].forEach(sel => window.AIF?.clearBusy?.(sel));
        return;
      }

      const codeBtn = event.target.closest('[data-code-tab]');
      if (codeBtn) {
        state.codeTab = codeBtn.dataset.codeTab;
        renderExamples();
        return;
      }

      const copyBtn = event.target.closest('[data-copy]');
      if (copyBtn) {
        copyText(copyBtn.dataset.copy, '已复制');
        return;
      }

      const copyCurrent = event.target.closest('[data-copy-current]');
      if (copyCurrent) {
        const model = selectedModel();
        const kind = copyCurrent.dataset.copyCurrent;
        const text = kind === 'endpoint' ? endpoint() : kind === 'model' ? model?.id : state.lastCode;
        copyText(text, kind === 'code' ? '代码已复制' : '已复制');
        return;
      }

      const buyBtn = event.target.closest('[data-buy-package]');
      if (buyBtn) buyPackage(buyBtn.dataset.buyPackage);
    });
  }

  function requestLoad() {
    // 【修改｜性能优化｜风险等级：低】控制台已改为主页懒加载，避免首屏重复拉取概览；并合并短时间内的重复刷新。
    if (loadingPromise) return loadingPromise;
    loadingPromise = load().finally(() => { loadingPromise = null; });
    return loadingPromise;
  }

  bindEvents();
  activateConsoleSection('docs', false);
  if (document.querySelector('.aif-console-page, [data-view-panel="console"]:not([hidden])')) requestLoad();
  window.addEventListener('aif:console-visible', requestLoad);
})();
