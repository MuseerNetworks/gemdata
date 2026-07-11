/**
 * GemData Service Worker v3
 * Production-safe: paths relative to domain root (/), not /gemdata/
 * Bumped to v3 to refresh PWA splash CSS/JS/manifest assets.
 */
const VERSION = 'gemdata-pwa-v3';
const ASSET_CACHE = `${VERSION}-assets`;
const PAGE_CACHE  = `${VERSION}-pages`;

// Production: basePath is empty string (domain root).
// If you ever move back to a sub-directory, set this to '/subdirectory'.
const BASE_PATH    = '';
const OFFLINE_PAGE = `${BASE_PATH}/offline.html`;

// Only cache true static shell assets — never cache PHP pages.
// PHP pages must always go network-first so auth/session state is respected.
const CORE_ASSETS = [
  `${BASE_PATH}/assets/css/site.css`,
  `${BASE_PATH}/assets/js/app.js`,
  `${BASE_PATH}/manifest.json`,
  `${BASE_PATH}/offline.html`,
  `${BASE_PATH}/assets/pwa/icons/icon-180.png`,
  `${BASE_PATH}/assets/pwa/icons/icon-192.png`,
  `${BASE_PATH}/assets/pwa/icons/icon-512.png`,
  `${BASE_PATH}/assets/pwa/icons/icon-maskable-512.png`,
];

// ─── Install: cache only static shell ─────────────────────────────────────
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(ASSET_CACHE)
      .then((cache) => cache.addAll(CORE_ASSETS))
      .then(() => self.skipWaiting())
      .catch((err) => {
        // Don't fail install if an icon is missing — log and continue
        console.warn('[GemData SW] Install partial failure:', err);
        return self.skipWaiting();
      })
  );
});

// ─── Activate: purge ALL old caches ───────────────────────────────────────
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys()
      .then((keys) => Promise.all(
        keys
          .filter((key) => key !== ASSET_CACHE && key !== PAGE_CACHE)
          .map((key) => {
            console.log('[GemData SW] Deleting stale cache:', key);
            return caches.delete(key);
          })
      ))
      .then(() => self.clients.claim())
  );
});

// ─── Messages ─────────────────────────────────────────────────────────────
self.addEventListener('message', (event) => {
  if (event.data && event.data.type === 'SKIP_WAITING') {
    self.skipWaiting();
  }
  if (event.data && event.data.type === 'QUEUE_SYNC_REQUEST') {
    event.waitUntil(notifyClients({ type: 'QUEUE_SYNC_REQUEST' }));
  }
});

// ─── Background Sync ──────────────────────────────────────────────────────
self.addEventListener('sync', (event) => {
  if (event.tag === 'gemdata-sync-queue') {
    event.waitUntil(notifyClients({ type: 'QUEUE_SYNC_REQUEST' }));
  }
});

// ─── Fetch ────────────────────────────────────────────────────────────────
self.addEventListener('fetch', (event) => {
  const request = event.request;

  // Only handle GET
  if (request.method !== 'GET') return;

  const url = new URL(request.url);
  const isSameOrigin = url.origin === self.location.origin;

  // Redirect any stale /gemdata/* requests to the root path
  // (handles old PWA installs that cached wrong URLs)
  if (isSameOrigin && url.pathname.startsWith('/gemdata/')) {
    const corrected = url.pathname.replace(/^\/gemdata/, '') || '/';
    const newUrl    = url.origin + corrected + url.search;
    event.respondWith(Response.redirect(newUrl, 301));
    return;
  }

  // Never intercept API, cron, or webhook routes — always network
  const isApiRoute  = isSameOrigin && url.pathname.startsWith(`${BASE_PATH}/api/`);
  const isCronRoute = isSameOrigin && url.pathname.startsWith(`${BASE_PATH}/cron/`);
  if (isApiRoute || isCronRoute) {
    event.respondWith(fetch(request));
    return;
  }

  // Tailwind CDN — let it pass through (don't cache; CDN handles its own caching)
  if (url.hostname === 'cdn.tailwindcss.com') {
    event.respondWith(fetch(request));
    return;
  }

  // Static assets → cache-first
  const isStaticAsset =
    url.pathname.includes('/assets/') ||
    url.pathname.endsWith('.css') ||
    url.pathname.endsWith('.js') ||
    url.pathname.endsWith('.png') ||
    url.pathname.endsWith('.jpg') ||
    url.pathname.endsWith('.jpeg') ||
    url.pathname.endsWith('.svg') ||
    url.pathname.endsWith('.webp') ||
    url.pathname.endsWith('.ico');

  const isManifest = isSameOrigin && url.pathname === `${BASE_PATH}/manifest.json`;

  if (isStaticAsset || isManifest) {
    event.respondWith(cacheFirst(request));
    return;
  }

  // Navigation (PHP pages) → always network-first, fallback to offline page
  if (request.mode === 'navigate') {
    event.respondWith(networkFirstPage(request));
    return;
  }

  // Everything else → network with runtime cache fallback
  event.respondWith(networkFirstRuntime(request));
});

// ─── Cache strategies ─────────────────────────────────────────────────────
async function cacheFirst(request) {
  const cache  = await caches.open(ASSET_CACHE);
  const cached = await cache.match(request, { ignoreSearch: true });
  if (cached) return cached;

  try {
    const response = await fetch(request);
    if (response && (response.ok || response.type === 'opaque')) {
      cache.put(request, response.clone());
    }
    return response;
  } catch (err) {
    return new Response('Asset unavailable offline.', { status: 503 });
  }
}

async function networkFirstPage(request) {
  try {
    const response = await fetch(request);
    // Only cache successful, non-authenticated page shells if needed
    // For VTU/fintech, we intentionally do NOT cache PHP pages to avoid
    // serving stale session/auth state
    return response;
  } catch (error) {
    // If completely offline, serve the offline page
    const cached = await caches.match(OFFLINE_PAGE);
    return cached || new Response('<h1>You are offline</h1>', {
      status: 503,
      headers: { 'Content-Type': 'text/html' }
    });
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
    if (cached) return cached;
    throw error;
  }
}

async function notifyClients(message) {
  const clients = await self.clients.matchAll({ includeUncontrolled: true, type: 'window' });
  clients.forEach((client) => client.postMessage(message));
}
