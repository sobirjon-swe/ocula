/* Only static build assets. Never cache customer API responses. */
importScripts('/sw-assets.js');
const assets = self.__SHELL_ASSETS__;
const CACHE = 'optika-customer-shell-' + assets.filter(path => path.startsWith('/assets/')).join('-');
self.addEventListener('install', event => event.waitUntil(caches.open(CACHE).then(cache => cache.addAll(assets)).then(() => self.skipWaiting())));
self.addEventListener('activate', event => event.waitUntil(caches.keys().then(keys => Promise.all(keys.filter(key => key.startsWith('optika-customer-shell-') && key !== CACHE).map(key => caches.delete(key)))).then(() => self.clients.claim())));
self.addEventListener('fetch', event => {
 const url = new URL(event.request.url);
 if (event.request.method !== 'GET' || url.origin !== self.location.origin || url.pathname.startsWith('/api/') || event.request.headers.has('Authorization')) return;
 if (event.request.mode === 'navigate') event.respondWith(fetch(event.request).catch(() => caches.match('/index.html')));
 else if (assets.includes(url.pathname)) event.respondWith(caches.match(event.request).then(cached => cached || fetch(event.request)));
});
