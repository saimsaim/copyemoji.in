// 1. Naya Monetag Config (Updated)
self.options = {
    "domain": "5gvci.com",
    "zoneId": 10852247
}
self.lary = ""
importScripts('https://5gvci.com/act/files/service-worker.min.js?r=sw')

// 2. Tera Purana Caching Logic (PWA)
const CACHE_NAME = 'copyemoji-cache-v1';
const urlsToCache = [
  '/',
  '/assets/css/style.css',
  '/assets/js/main.js',
  '/assets/data/emoji.json'
];

self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME).then(cache => {
      return cache.addAll(urlsToCache);
    })
  );
});

self.addEventListener('fetch', event => {
  event.respondWith(
    caches.match(event.request).then(response => {
      return response || fetch(event.request);
    })
  );
});