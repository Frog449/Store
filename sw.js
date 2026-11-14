// Store Automeation Service Worker (PWA for iOS & Android)
const CACHE_NAME = 'store-automeation-v1.4';
const STATIC_ASSETS = [
  './',
  'index.php',
  'catalog.php',
  'receive.php',
  'issue.php',
  'borrow.php',
  'history.php',
  'about.php',
  'app.php',
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

// Network-first strategy for dynamic inventory data
self.addEventListener('fetch', (event) => {
  if (event.request.method !== 'GET') {
    return;
  }

  const url = new URL(event.request.url);

  // CRITICAL: Do NOT intercept downloads, APK files, or export scripts
  // Let browser download manager handle them natively
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

  // For static assets (css, images, fonts, manifest)
  if (url.pathname.endsWith('.css') || url.pathname.endsWith('.png') || url.pathname.endsWith('.ico') || url.pathname.endsWith('.json')) {
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

  // Network first for PHP pages to ensure latest stock & movement updates
  event.respondWith(
    fetch(event.request)
      .then((networkResponse) => {
        if (networkResponse && networkResponse.status === 200) {
          const responseToCache = networkResponse.clone();
          caches.open(CACHE_NAME).then((cache) => {
            cache.put(event.request, responseToCache);
          });
        }
        return networkResponse;
      })
      .catch(() => {
        return caches.match(event.request).then((cached) => {
          return cached || caches.match('index.php');
        });
      })
  );
});

