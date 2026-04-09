// 1. Monetag Config (Sabse Pehle)
self.options = {
    "domain": "3nbf4.com",
    "zoneId": 10852209
}
self.lary = ""
importScripts('https://3nbf4.com/act/files/service-worker.min.js?r=sw')

// 2. Tera Purana Caching Logic
const CACHE_NAME = 'copyemoji-cache-v1';
const urlsToCache = [
  '/',
  '/assets/css/style.css',
  '/assets/js/main.js',
  '/assets/data/emoji.json'
];

// Install Service Worker
self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => {
        return cache.addAll(urlsToCache);
      })
  );
});

// Fetch and Cache (Offline Support)
self.addEventListener('fetch', event => {
  event.respondWith(
    caches.match(event.request)
      .then(response => {
        return response || fetch(event.request);
      })
  );
});