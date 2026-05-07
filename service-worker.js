const VERSION = 'gemdata-pwa-v1';
const ASSET_CACHE = `${VERSION}-assets`;
const PAGE_CACHE = `${VERSION}-pages`;
const RUNTIME_CONFIG = {
  basePath: '/gemdata',
  offlinePage: '/gemdata/offline.html'
};

const CORE_ASSETS = [
  '/gemdata/',
  '/gemdata/index.php',
  '/gemdata/user/login.php',
  '/gemdata/user/register.php',
  '/gemdata/user/forgot-password.php',
  '/gemdata/assets/css/site.css',
  '/gemdata/assets/js/app.js',
  '/gemdata/manifest.json',
  '/gemdata/offline.html',
  '/gemdata/assets/pwa/icons/icon-180.png',
  '/gemdata/assets/pwa/icons/icon-192.png',
  '/gemdata/assets/pwa/icons/icon-512.png',
  '/gemdata/assets/pwa/icons/icon-maskable-512.png'
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(ASSET_CACHE).then((cache) => cache.addAll(CORE_ASSETS)).then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) => Promise.all(
      keys
        .filter((key) => ![ASSET_CACHE, PAGE_CACHE].includes(key))
        .map((key) => caches.delete(key))
    )).then(() => self.clients.claim())
  );
});

self.addEventListener('message', (event) => {
  if (event.data && event.data.type === 'SKIP_WAITING') {
    self.skipWaiting();
  }

  if (event.data && event.data.type === 'QUEUE_SYNC_REQUEST') {
    event.waitUntil(notifyClients({ type: 'QUEUE_SYNC_REQUEST' }));
  }
});

self.addEventListener('sync', (event) => {
  if (event.tag === 'gemdata-sync-queue') {
    event.waitUntil(notifyClients({ type: 'QUEUE_SYNC_REQUEST' }));
  }
});

self.addEventListener('fetch', (event) => {
  const request = event.request;
  if (request.method !== 'GET') {
    return;
  }

  const url = new URL(request.url);
  const isSameOrigin = url.origin === self.location.origin;
  const isApiRoute = isSameOrigin && url.pathname.startsWith(`${RUNTIME_CONFIG.basePath}/api/`);
  const isCronRoute = isSameOrigin && url.pathname.startsWith(`${RUNTIME_CONFIG.basePath}/cron/`);
  const isManifest = isSameOrigin && url.pathname === `${RUNTIME_CONFIG.basePath}/manifest.json`;
  const isStaticAsset =
    url.pathname.includes('/assets/') ||
    url.pathname.endsWith('.css') ||
    url.pathname.endsWith('.js') ||
    url.pathname.endsWith('.png') ||
    url.pathname.endsWith('.jpg') ||
    url.pathname.endsWith('.jpeg') ||
    url.pathname.endsWith('.svg') ||
    url.pathname.endsWith('.webp') ||
    url.hostname === 'cdn.tailwindcss.com';

  if (isApiRoute || isCronRoute) {
    event.respondWith(fetch(request));
    return;
  }

  if (isStaticAsset || isManifest) {
    event.respondWith(cacheFirst(request));
    return;
  }

  if (request.mode === 'navigate') {
    event.respondWith(networkFirstPage(request));
    return;
  }

  event.respondWith(networkFirstRuntime(request));
});

async function cacheFirst(request) {
  const cache = await caches.open(ASSET_CACHE);
  const cached = await cache.match(request, { ignoreSearch: true });
  if (cached) {
    return cached;
  }

  const response = await fetch(request);
  if (response && (response.ok || response.type === 'opaque')) {
    cache.put(request, response.clone());
  }
  return response;
}

async function networkFirstPage(request) {
  const cache = await caches.open(PAGE_CACHE);
  try {
    const response = await fetch(request);
    if (response && response.ok) {
      cache.put(request, response.clone());
    }
    return response;
  } catch (error) {
    const cached = await cache.match(request, { ignoreSearch: false }) || await cache.match(request.url) || await cache.match(`${RUNTIME_CONFIG.basePath}/`);
    if (cached) {
      return cached;
    }
    return caches.match(RUNTIME_CONFIG.offlinePage);
  }
}

async function networkFirstRuntime(request) {
  const cache = await caches.open(PAGE_CACHE);
  try {
    const response = await fetch(request);
    if (response && response.ok) {
      cache.put(request, response.clone());
    }
    return response;
  } catch (error) {
    const cached = await cache.match(request);
    if (cached) {
      return cached;
    }
    throw error;
  }
}

async function notifyClients(message) {
  const clients = await self.clients.matchAll({ includeUncontrolled: true, type: 'window' });
  clients.forEach((client) => client.postMessage(message));
}
