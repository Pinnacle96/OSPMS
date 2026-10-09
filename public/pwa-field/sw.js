/* Only anonymous static assets are cached. Authenticated responses never enter CacheStorage. */
const PREFIX = 'ospm-field-shell-';
const CACHE = PREFIX + 'v3';
const SHELL = '/pwa-field/offline.html';
const STATIC = [SHELL, '/pwa-field/manifest.webmanifest', '/pwa-field/icons/icon-192.png', '/pwa-field/icons/icon-512.png', '/pwa-field/icons/maskable-512.png'];
self.addEventListener('install', event => {
    event.waitUntil(caches.open(CACHE).then(cache => cache.addAll(STATIC.map(url => new Request(url, {cache: 'reload', credentials: 'omit'})))));
    self.skipWaiting();
});
self.addEventListener('activate', event => {
    event.waitUntil(caches.keys().then(keys => Promise.all(keys.filter(key => key.startsWith(PREFIX) && key !== CACHE).map(key => caches.delete(key)))).then(() => self.clients.claim()));
});
self.addEventListener('fetch', event => {
    const request = event.request;
    const url = new URL(request.url);
    if (request.method !== 'GET' || url.origin !== self.location.origin) return;
    if (request.mode === 'navigate' && url.pathname.startsWith('/field/')) {
        event.respondWith(fetch(request).catch(() => caches.match(SHELL)));
        return;
    }
    // Inertia JSON, tokens, query strings and dynamic pages are never cached.
    if (request.headers.has('X-Inertia') || url.search || !STATIC.includes(url.pathname)) return;
    event.respondWith(caches.match(request).then(cached => cached || fetch(request)));
});
