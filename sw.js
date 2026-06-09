// ============================================================
// EGGLAND BD — Service Worker (PWA Offline Support)
// ============================================================

const CACHE_NAME = 'egglandbd-v1.0.1';
const OFFLINE_URL = '/egglandbd/offline.html';

const PRECACHE_ASSETS = [
  '/egglandbd/',
  '/egglandbd/index.php',
  '/egglandbd/assets/css/app.css',
  '/egglandbd/assets/js/app.js',
  '/egglandbd/assets/js/map.js',
  '/egglandbd/assets/images/logo.png',
  '/egglandbd/offline.html',
  'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css',
  'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css',
  'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js',
];

// ── Install ────────────────────────────────────────────────
self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME).then(cache => {
      console.log('[SW] Pre-caching assets');
      return cache.addAll(PRECACHE_ASSETS.map(url => {
        return new Request(url, { mode: 'no-cors' });
      }));
    }).catch(err => console.warn('[SW] Pre-cache failed:', err))
  );
  self.skipWaiting();
});

// ── Activate ───────────────────────────────────────────────
self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(keys =>
      Promise.all(keys.filter(k => k !== CACHE_NAME).map(k => caches.delete(k)))
    )
  );
  self.clients.claim();
});

// ── Fetch Strategy ─────────────────────────────────────────
self.addEventListener('fetch', event => {
  const url = new URL(event.request.url);

  // Skip API calls (always network-first)
  if (url.pathname.includes('/api/')) {
    event.respondWith(networkWithFallback(event.request));
    return;
  }

  // For HTML navigation
  if (event.request.mode === 'navigate') {
    event.respondWith(
      fetch(event.request).catch(() => caches.match(OFFLINE_URL))
    );
    return;
  }

  // Cache-first for static assets
  event.respondWith(cacheFirst(event.request));
});

async function cacheFirst(request) {
  const cached = await caches.match(request);
  if (cached) return cached;
  try {
    const response = await fetch(request);
    if (response.ok) {
      const cache = await caches.open(CACHE_NAME);
      cache.put(request, response.clone());
    }
    return response;
  } catch {
    return caches.match(OFFLINE_URL);
  }
}

async function networkWithFallback(request) {
  try {
    return await fetch(request);
  } catch {
    // If offline and it's a GET, try cache
    if (request.method === 'GET') {
      const cached = await caches.match(request);
      if (cached) return cached;
    }
    return new Response(JSON.stringify({ success: false, message: 'You are offline. Please check your connection.' }), {
      headers: { 'Content-Type': 'application/json' }
    });
  }
}

// ── Background Sync ────────────────────────────────────────
self.addEventListener('sync', event => {
  if (event.tag === 'sync-orders') {
    event.waitUntil(syncOfflineOrders());
  }
});

async function syncOfflineOrders() {
  // Sync any queued offline orders when back online
  console.log('[SW] Syncing offline orders...');
}

// ── Push Notifications ─────────────────────────────────────
self.addEventListener('push', event => {
  const data = event.data?.json() || {};
  event.waitUntil(
    self.registration.showNotification(data.title || 'Eggland BD', {
      body: data.message || 'You have a new notification',
      icon: '/egglandbd/assets/images/logo.png',
      badge: '/egglandbd/assets/images/logo.png',
      data: { url: data.url || '/egglandbd/' },
    })
  );
});

self.addEventListener('notificationclick', event => {
  event.notification.close();
  event.waitUntil(clients.openWindow(event.notification.data.url));
});
