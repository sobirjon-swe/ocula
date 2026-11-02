import { test } from 'node:test'
import assert from 'node:assert/strict'
import http from 'node:http'
import { spawn } from 'node:child_process'
import { once } from 'node:events'

test('four locale SSR pages, maps, metadata and booking recovery', async () => {
  const branch = { id: 1, name: 'Test branch', address: 'Test address', phone: '+998901234567', open_time: '09:00', close_time: '22:00', lat: '41.3', lng: '69.2' }
  const backend = http.createServer((req, res) => {
    res.setHeader('Content-Type', 'application/json')
    if (req.url.endsWith('/branches')) res.end(JSON.stringify({ data: [branch] }))
    else if (req.url.endsWith('/services')) res.end(JSON.stringify({ data: [{ id: 1, name: 'Service ' + req.headers['accept-language'], type: 'exam', price: '100000.00', price_formatted: '100 000', duration_min: 20 }] }))
    else { res.statusCode = 201; res.end('{}') }
  })
  backend.listen(18001, '127.0.0.1'); await once(backend, 'listening')
  const server = spawn(process.execPath, ['node_modules/next/dist/bin/next', 'start', '-p', '3101'], { cwd: new URL('..', import.meta.url), env: { ...process.env, BACKEND_API_URL: 'http://127.0.0.1:18001/api/v1', CUSTOMER_APP_URL:'https://customer.test' }, stdio: ['ignore', 'pipe', 'pipe'] })
  try {
    await new Promise((resolve, reject) => {
      let output = ''
      const timer = setTimeout(() => reject(Error('Next start timeout')), 15000)
      server.stdout.on('data', data => { output += data; if (output.includes('Ready')) { clearTimeout(timer); resolve() } })
      server.once('exit', code => { clearTimeout(timer); reject(Error('Next exit ' + code)) })
    })
    const titles = { 'uz-latn': 'Ko&#x27;zoynak', 'uz-cyrl': 'Кўзойнак', ru: 'Очки', en: 'Eyewear' }
    for (const locale of ['uz-latn', 'uz-cyrl', 'ru', 'en']) {
      const response = await fetch('http://127.0.0.1:3101/' + locale)
      assert.equal(response.status, 200)
      const html = await response.text()
      assert.ok(html.includes(titles[locale]), locale + ' translated SSR')
      assert.ok(html.includes('rel="canonical"'))
      assert.ok(html.includes('hrefLang="uz-cyrl"'))
      assert.ok(html.includes('<html lang="' + locale + '"'))
      const branches = await fetch('http://127.0.0.1:3101/' + locale + '/branches').then(r => r.text())
      assert.ok(branches.includes('yandex.uz/maps/'))
      assert.ok(branches.includes('69.2%2C41.3'))
      assert.ok(branches.includes('https://customer.test/check-in?branch=1'))
      assert.ok(branches.includes('data:image/png;base64,'))
      const prices = await fetch('http://127.0.0.1:3101/' + locale + '/prices').then(r => r.text())
      assert.ok(prices.includes('Service ' + locale))
      const booking = await fetch('http://127.0.0.1:3101/' + locale + '/booking').then(r => r.text())
      assert.ok(booking.includes('Test branch'))
      assert.ok(booking.includes('autoComplete="tel"'))
    }
    const endpoint = 'http://127.0.0.1:3101/api/appointments'
    assert.equal((await fetch(endpoint, { method: 'POST', body: 'not-json' })).status, 400)
    assert.equal((await fetch(endpoint, { method: 'POST', body: '{}' })).status, 422)
    const payload = { branch_id: 1, name: 'Test customer', phone: '+998901234567' }
    const sent = await fetch(endpoint, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) })
    assert.equal(sent.status, 201); assert.equal((await sent.json()).ok, true)
    await new Promise(resolve => backend.close(resolve))
    const unavailable = await fetch(endpoint, { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept-Language': 'en' }, body: JSON.stringify(payload) })
    assert.equal(unavailable.status, 503)
    assert.match((await unavailable.json()).message, /Check your connection/)
  } finally { server.kill(); backend.close() }
})
