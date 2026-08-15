// Captain PWA service worker. Deliberately scoped to /captain/ by its own
// location — service workers can only control paths at or below where
// they're served from, so placing this file here (rather than at the
// site root) keeps it from ever touching the public customer-facing site.

const CACHE_NAME = 'capitony-captain-v1';
const APP_SHELL = [
  '/captain/dashboard.php',
  '/assets/css/app.css',
  '/assets/img/logo.png',
  '/captain/offline.html',
];

self.addEventListener('install', function (event) {
  event.waitUntil(
    caches.open(CACHE_NAME).then(function (cache) {
      return cache.addAll(APP_SHELL);
    })
  );
  self.skipWaiting();
});

self.addEventListener('activate', function (event) {
  event.waitUntil(
    caches.keys().then(function (names) {
      return Promise.all(
        names.filter(function (n) { return n !== CACHE_NAME; }).map(function (n) { return caches.delete(n); })
      );
    })
  );
  self.clients.claim();
});

self.addEventListener('fetch', function (event) {
  var req = event.request;

  // Never intercept anything but GET — POST (form submissions, API calls)
  // must always go straight to the network. Offline queuing for those is
  // handled separately on the page itself (see offline-queue.js), not here.
  if (req.method !== 'GET') {
    return;
  }

  // Page navigations: try the network first (captains want fresh data,
  // not a stale cached page, whenever there's a real connection), fall
  // back to cache, then to a plain offline notice as the last resort.
  if (req.mode === 'navigate') {
    event.respondWith(
      fetch(req)
        .then(function (res) {
          var resClone = res.clone();
          caches.open(CACHE_NAME).then(function (cache) { cache.put(req, resClone); });
          return res;
        })
        .catch(function () {
          return caches.match(req).then(function (cached) {
            return cached || caches.match('/captain/offline.html');
          });
        })
    );
    return;
  }

  // Static assets (CSS, images): cache-first, since these rarely change
  // and there's no benefit to re-fetching them over a slow connection.
  event.respondWith(
    caches.match(req).then(function (cached) {
      return cached || fetch(req).then(function (res) {
        var resClone = res.clone();
        caches.open(CACHE_NAME).then(function (cache) { cache.put(req, resClone); });
        return res;
      });
    })
  );
});
