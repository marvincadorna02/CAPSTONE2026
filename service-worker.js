const CACHE = 'fixitdavao-v2';
const OFFLINE_URLS = [
  'login.php',
  'assets/css/dashboard.css',
  'assets/images/logo.png',
];

self.addEventListener('install', e => {
  e.waitUntil(
    caches.open(CACHE).then(c => c.addAll(OFFLINE_URLS))
  );
  self.skipWaiting();
});

self.addEventListener('activate', e => {
  e.waitUntil(
    caches.keys().then(keys =>
      Promise.all(keys.filter(k => k !== CACHE).map(k => caches.delete(k)))
    )
  );
  self.clients.claim();
});

self.addEventListener('fetch', e => {
  // Skip non-GET, browser extension requests, and API calls (never cache API responses)
  if (
    e.request.method !== 'GET' ||
    !e.request.url.startsWith('http') ||
    e.request.url.includes('/api/')
  ) return;

  e.respondWith(
    fetch(e.request)
      .then(res => {
        // Cache successful responses
        if (res.ok) {
          const clone = res.clone();
          caches.open(CACHE).then(c => c.put(e.request, clone));
        }
        return res;
      })
      .catch(() => caches.match(e.request))
  );
});