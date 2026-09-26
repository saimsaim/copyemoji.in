// CopyEmoji.in - Clean Service Worker v1.5 (no third-party imports)
// Strategy: precache core shell only, network-first HTML, SWR for CSS/JS/icons
const CACHE_NAME = 'copyemoji-v1.5';
const CORE = [
  '/',
  '/assets/css/style.css',
  '/assets/js/main.js'
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => cache.addAll(CORE)).then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys()
      .then((keys) => Promise.all(keys.filter((k) => k !== CACHE_NAME).map((k) => caches.delete(k))))
      .then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (event) => {
  const url = new URL(event.request.url);
  if (event.request.method !== 'GET') return;
  if (url.origin !== self.location.origin) return;

  // Never precache 1.4MB emoji.json - network-first with cache fallback
  if (url.pathname.includes('/assets/data/')) {
    event.respondWith(fetch(event.request).catch(() => caches.match(event.request)));
    return;
  }

  // CSS/JS/icons/fonts: cache-first, update in background
  if (url.pathname.match(/\.(css|js|png|woff2?|webp|svg)$/)) {
    event.respondWith(caches.match(event.request).then((hit) => hit || fetch(event.request)));
    return;
  }

  // HTML navigations: network-first
  event.respondWith(fetch(event.request).catch(() => caches.match(event.request)));
});
