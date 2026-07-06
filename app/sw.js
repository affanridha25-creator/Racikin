// Service worker Racikin — cache shell, biarkan API selalu ke jaringan.
const CACHE = 'racikin-v27';
const ASSETS = ['./', './index.php', './manifest.webmanifest', './icons/favicon.png', './icons/icon-192.png', './icons/icon-512.png'];

self.addEventListener('install', e => {
  e.waitUntil(
    caches.open(CACHE).then(c => c.addAll(ASSETS)).then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', e => {
  e.waitUntil(
    caches.keys().then(ks => Promise.all(ks.filter(k => k !== CACHE).map(k => caches.delete(k))))
      .then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', e => {
  const req = e.request;
  if (req.method !== 'GET') return;                       // api.php (POST) → langsung ke jaringan
  const url = new URL(req.url);
  if (url.pathname.endsWith('/api.php')) return;          // jangan cache data API
  // network-first: selalu coba jaringan (biar update tampil), fallback ke cache saat offline
  e.respondWith(
    fetch(req).then(res => {
      const copy = res.clone();
      caches.open(CACHE).then(c => c.put(req, copy)).catch(() => {});
      return res;
    }).catch(() => caches.match(req).then(r => r || caches.match('./index.php')))
  );
});
