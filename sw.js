// Store Automeation Service Worker (PWA for iOS & Android)
const CACHE_NAME = 'store-automeation-v1.5';
const STATIC_ASSETS = [
  'style.css',
  'assets/logo.png',
  'assets/favicon.png',
  'favicon.ico',
  'assets/icons/icon-192.png',
  'assets/icons/icon-512.png',
  'assets/icons/apple-touch-icon.png',
  'manifest.json'
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => {
      return cache.addAll(STATIC_ASSETS).catch((err) => {
        console.warn('PWA Pre-cache skipped some items:', err);
      });
    })
  );
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) => {
      return Promise.all(
        keys.map((key) => {
          if (key !== CACHE_NAME) {
            return caches.delete(key);
          }
        })
      );
    })
  );
  self.clients.claim();
});

self.addEventListener('fetch', (event) => {
  if (event.request.method !== 'GET') {
    return;
  }

  const url = new URL(event.request.url);

  // 1. NEVER intercept downloads, APK files, or export scripts
  if (
    url.pathname.includes('/downloads/') ||
    url.pathname.endsWith('.apk') ||
    url.pathname.endsWith('.zip') ||
    url.pathname.includes('download.php') ||
    url.pathname.includes('export.php') ||
    url.pathname.includes('backup.php')
  ) {
    return;
  }

  // 2. Dynamic PHP pages & navigations: NEVER cache, ALWAYS fetch direct live data from server
  if (
    event.request.mode === 'navigate' ||
    url.pathname.endsWith('.php') ||
    url.pathname === '/' ||
    url.pathname.endsWith('/Store/') ||
    url.search !== ''
  ) {
    // Pass directly to network with no-store cache directive
    event.respondWith(
      fetch(event.request, { cache: 'no-store' }).catch(() => {
        return caches.match('index.php').then((res) => {
          return res || new Response('ออฟไลน์: ไม่สามารถเชื่อมต่อกับเซิร์ฟเวอร์ได้ในขณะนี้', {
            status: 503,
            headers: { 'Content-Type': 'text/plain; charset=utf-8' }
          });
        });
      })
    );
    return;
  }

  // 3. Static assets only (css, images, fonts, manifest)
  if (
    url.pathname.endsWith('.css') ||
    url.pathname.endsWith('.png') ||
    url.pathname.endsWith('.jpg') ||
    url.pathname.endsWith('.jpeg') ||
    url.pathname.endsWith('.ico') ||
    url.pathname.endsWith('.json')
  ) {
    event.respondWith(
      caches.match(event.request).then((cached) => {
        return cached || fetch(event.request).then((response) => {
          if (response && response.status === 200) {
            const copy = response.clone();
            caches.open(CACHE_NAME).then((cache) => cache.put(event.request, copy));
          }
          return response;
        });
      })
    );
    return;
  }
});
