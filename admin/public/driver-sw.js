const CACHE = 'optika-driver-shell-v3'
self.addEventListener('install', event => {
  event.waitUntil((async () => {
    const cache = await caches.open(CACHE)
    const response = await fetch('/index.html', { cache: 'reload' })
    if (!response.ok) throw new Error('Shell unavailable')
    await cache.put('/index.html', response.clone())
    const html = await response.text()
    const assets = [...html.matchAll(/(?:src|href)=["'](\/assets\/[^"']+)["']/g)].map(match => match[1])
    await cache.addAll([...assets, '/manifest.webmanifest', '/optika-icon.svg'])
    await self.skipWaiting()
  })())
})
self.addEventListener('activate', event => {
  event.waitUntil((async () => {
    const keys = await caches.keys()
    await Promise.all(keys.filter(key => key.startsWith('optika-driver-shell-') && key !== CACHE).map(key => caches.delete(key)))
    await self.clients.claim()
  })())
})
self.addEventListener('fetch', event => {
  const request = event.request
  const url = new URL(request.url)
  // API responses and credentials never enter Cache Storage.
  if (request.method !== 'GET' || url.origin !== self.location.origin || url.pathname.startsWith('/api/')) return
  if (request.mode === 'navigate') {
    event.respondWith(fetch(request).catch(() => caches.match('/index.html', {ignoreVary:true})))
  } else if (url.pathname.startsWith('/assets/') || ['/manifest.webmanifest', '/optika-icon.svg'].includes(url.pathname)) {
    event.respondWith(caches.match(request, {ignoreVary:true}).then(cached => cached || fetch(request).then(response => {
      if (response.ok) { const copy = response.clone(); void caches.open(CACHE).then(cache => cache.put(request, copy)) }
      return response
    })))
  }
})
