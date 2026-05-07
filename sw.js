const CACHE_VERSION = 'newapi-m3-v20260506';
const STATIC_CACHE = `${CACHE_VERSION}-static`;
const RUNTIME_CACHE = `${CACHE_VERSION}-runtime`;
const QUEUE_CACHE = `${CACHE_VERSION}-offline-queue`;
const STATIC_ASSETS = [
  './',
  './index.html',
  './console.html',
  './account.html',
  './redeem.html',
  './developer-plan.html',
  './offline.html',
  './manifest.webmanifest',
  './assets/ai_site.css',
  './assets/css/layout.css',
  './assets/css/components.css',
  './assets/css/themes.css',
  './assets/css/mobile.css',
  './assets/icons-fallback.css',
  './assets/aif_shared.js',
  './assets/aif_pwa.js',
  './assets/ai_site.js',
  './assets/ai_console.js',
  './assets/ai_auth.js',
  './assets/ai_redeem.js',
  './assets/ai_theme.js',
  './assets/developer_plan.css',
  './assets/developer_plan.js',
  './assets/icon-192.svg',
  './assets/icon-512.svg'
];

self.addEventListener('install', event => {
  event.waitUntil(caches.open(STATIC_CACHE).then(cache => cache.addAll(STATIC_ASSETS)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', event => {
  event.waitUntil(caches.keys().then(keys => Promise.all(keys.filter(key => !key.startsWith(CACHE_VERSION)).map(key => caches.delete(key)))).then(() => self.clients.claim()));
});

async function cacheFirst(request) {
  const cached = await caches.match(request);
  if (cached) return cached;
  const response = await fetch(request);
  if (response && response.ok) {
    const cache = await caches.open(RUNTIME_CACHE);
    cache.put(request, response.clone());
  }
  return response;
}

async function networkFirst(request) {
  try {
    const response = await fetch(request);
    if (response && response.ok) {
      const cache = await caches.open(RUNTIME_CACHE);
      cache.put(request, response.clone());
    }
    return response;
  } catch (error) {
    return (await caches.match(request)) || (await caches.match('./offline.html')) || new Response('Offline', { status: 503 });
  }
}

async function queuePost(request) {
  const clone = request.clone();
  const body = await clone.text();
  const entry = new Response(JSON.stringify({
    url: request.url,
    method: request.method,
    headers: Array.from(request.headers.entries()),
    body,
    queued_at: Date.now()
  }), { headers: { 'Content-Type': 'application/json' } });
  const cache = await caches.open(QUEUE_CACHE);
  await cache.put(`queued-${Date.now()}-${Math.random()}`, entry);
  if ('sync' in self.registration) await self.registration.sync.register('aif-replay-queue');
  return new Response(JSON.stringify({ ok: false, offline: true, msg: '请求已离线暂存，联网后自动重试。' }), { status: 202, headers: { 'Content-Type': 'application/json; charset=utf-8' } });
}

async function replayQueue() {
  const cache = await caches.open(QUEUE_CACHE);
  const requests = await cache.keys();
  for (const req of requests) {
    const data = await (await cache.match(req)).json();
    try {
      const res = await fetch(data.url, { method: data.method, headers: data.headers, body: data.body || undefined, credentials: 'same-origin' });
      if (res.ok) await cache.delete(req);
    } catch (error) {
      // Keep the item for next sync.
    }
  }
}

self.addEventListener('sync', event => {
  if (event.tag === 'aif-replay-queue') event.waitUntil(replayQueue());
});

self.addEventListener('fetch', event => {
  const request = event.request;
  const url = new URL(request.url);
  if (request.method === 'POST' && url.origin === location.origin && /ai_api_console_api\.php|auth\.php|v1\//.test(url.pathname)) {
    event.respondWith(fetch(request.clone()).catch(() => queuePost(request)));
    return;
  }
  if (request.method !== 'GET') return;
  if (url.origin === location.origin && /\.(?:css|js|svg|png|webp|avif|woff2?)$/i.test(url.pathname)) {
    event.respondWith(cacheFirst(request));
    return;
  }
  if (request.mode === 'navigate' || url.origin === location.origin) {
    event.respondWith(networkFirst(request));
  }
});
