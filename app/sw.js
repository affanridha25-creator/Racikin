// Service worker Racikin — cache shell, biarkan API selalu ke jaringan.
const CACHE = 'racikin-v36';
const ASSETS = ['./', './index.php', './manifest.webmanifest', './icons/favicon.png', './icons/icon-192.png', './icons/icon-512.png',
  './css/base.css', './css/dashboard.css', './css/login.css', './css/dashboard-v2.css', './css/login-outro.css', './css/toko.css', './css/profil.css', './css/components.css', './css/pos.css', './css/print.css', './css/langganan.css', './css/base-v2.css', './css/p-keuangan.css', './css/p-produksi.css', './css/p-bahan.css', './css/p-produk.css', './css/p-kasir.css', './css/p-distribusi.css', './css/p-pembayaran.css', './css/p-rekap.css', './css/p-pengguna.css', './css/p-export.css', './js/core.js', './js/offline.js', './js/tenant.js', './js/keuangan.js', './js/export-csv.js', './js/derived.js', './js/nav.js', './js/dashboard.js', './js/qris.js', './js/pos.js', './js/print-thermal.js', './js/produksi.js', './js/distribusi.js', './js/pembayaran.js', './js/bahan.js', './js/produk.js', './js/toko.js', './js/rekap.js', './js/pengguna.js', './js/profil.js', './js/export-data.js', './js/auth.js', './js/langganan.js', './js/push.js', './js/boot.js'];

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
