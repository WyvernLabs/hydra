/*
 * Hydra: service worker of the installable panel (addon "pwa"). Nothing of the
 * panel is cached, every request goes to the network as usual; only when the
 * network is gone, pages show a short offline notice instead of the browser's
 * error page.
 */
const OFFLINE = '/hydra/offline.html';
const CACHE = 'hydra-offline-v1';

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches
            .open(CACHE)
            .then((cache) => cache.add(new Request(OFFLINE, { cache: 'reload' })))
            .then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches
            .keys()
            .then((keys) => Promise.all(keys.filter((key) => key !== CACHE).map((key) => caches.delete(key))))
            .then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    if (event.request.mode !== 'navigate') {
        return;
    }

    event.respondWith(fetch(event.request).catch(() => caches.match(OFFLINE)));
});
