const CACHE_NAME = 'copyemoji-cache-v1';
const urlsToCache = [
  '/',
  '/assets/css/style.css',
  '/assets/js/main.js',
  '/assets/data/emoji.json'
];

// 🛠️ Install Service Worker
self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => {
        return cache.addAll(urlsToCache);
      })
  );
});

// ⚡ Fetch and Cache (Offline Support)
self.addEventListener('fetch', event => {
  event.respondWith(
    caches.match(event.request)
      .then(response => {
        // Agar cache mein hai toh turant do, warna net se load karo
        return response || fetch(event.request);
      })
  );
});