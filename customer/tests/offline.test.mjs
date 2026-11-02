import { test } from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import vm from 'node:vm'
import ts from 'typescript'
import { toCyrillic } from '../src/lib/i18n/transliterate.ts'

function worker() {
  const handlers = {}, cached = [], removed = []
  const context = {
    URL, Promise, importScripts() {},
    self: {
      __SHELL_ASSETS__: ['/', '/index.html', '/assets/app.js'],
      location: { origin: 'https://customer.test' },
      addEventListener: (name, fn) => { handlers[name] = fn },
      skipWaiting: async () => {}, clients: { claim: async () => {} },
    },
    caches: {
      open: async () => ({ addAll: async assets => cached.push(...assets) }),
      match: async path => ({ shell: path }),
      keys: async () => ['optika-customer-shell-old', 'unrelated-cache'],
      delete: async key => removed.push(key),
    },
    fetch: async () => { throw Error('offline') },
  }
  vm.runInNewContext(readFileSync(new URL('../public/sw.js', import.meta.url), 'utf8'), context)
  return { handlers, cached, removed }
}
const request = (url, mode = 'cors', auth = false, method = 'GET') => ({ url, mode, method, headers: { has: () => auth } })

test('private requests never enter shell cache', () => {
  const { handlers } = worker()
  for (const req of [request('https://customer.test/api/v1/customer/orders'), request('https://customer.test/assets/app.js', 'cors', true), request('https://api.test/customer'), request('https://customer.test/assets/app.js', 'cors', false, 'POST')]) {
    let intercepted = false
    handlers.fetch({ request: req, respondWith: () => { intercepted = true } })
    assert.equal(intercepted, false)
  }
})
test('offline navigation returns public index shell', async () => {
  const { handlers } = worker()
  let response
  handlers.fetch({ request: request('https://customer.test/orders/2', 'navigate'), respondWith: p => { response = p } })
  assert.deepEqual(await response, { shell: '/index.html' })
})
test('install and activation cache only allowlist and remove only obsolete app caches', async () => {
  const { handlers, cached, removed } = worker()
  let pending
  handlers.install({ waitUntil: p => { pending = p } }); await pending
  assert.deepEqual(cached, ['/', '/index.html', '/assets/app.js'])
  handlers.activate({ waitUntil: p => { pending = p } }); await pending
  assert.deepEqual(removed, ['optika-customer-shell-old'])
})
test('derived Cyrillic preserves medical notation and handles apostrophes', () => {
  assert.equal(toCyrillic("O‘zbekcha — qo'ng'iroq; SPH CYL AXIS ADD PD OPTIKA"), 'Ўзбекча — қўнғироқ; SPH CYL AXIS ADD PD OPTIKA')
  assert.equal(toCyrillic('Boshlash'), 'Бошлаш')
})

test('prescription export preserves all eye parameters and long medical notes', () => {
  const source = readFileSync(new URL('../src/features/prescriptions/export.ts', import.meta.url), 'utf8')
  const code = ts.transpileModule(source, { compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2022 } }).outputText
  const drawn = []
  const canvas = { width: 0, height: 0, getContext: () => ({ measureText: text => ({ width: text.length * 16 }), fillRect() {}, fillText: (text, x, y) => drawn.push({ text, x, y }) }) }
  const context = { exports: {}, require: () => ({ formatDate: value => value }), document: { createElement: () => canvas } }
  vm.runInNewContext(code, context)
  const notes = 'A'.repeat(2000) + '\nKeep every instruction.'
  context.exports.prescriptionImage({ id: 1, od: { sph: '-2.25', cyl: '-0.75', axis: 175, add: '1.25' }, os: { sph: '-1.50', cyl: '-0.50', axis: 90, add: '1.00' }, pd: '62', pd_near: '59', prism: '2 base in', notes, doctor: 'Test doctor', branch: 'Test branch', created_at: '2026-10-30', valid_until: '2027-10-30', is_expired: false }, { title: 'Prescription', valid: 'Valid', expired: 'Expired', near: 'Near', prism: 'Prism' })
  for (const value of ['-2.25', '-0.75', '175', '1.25', '-1.50', '-0.50', '90', '1.00', 'Prism: 2 base in']) assert.ok(drawn.some(line => line.text === value), value)
  assert.equal(drawn.filter(line => /^A+$/.test(line.text)).map(line => line.text).join('').length, 2000)
  assert.ok(drawn.some(line => line.text === 'Keep every instruction.'))
  assert.ok(drawn.every(line => line.y < canvas.height))
})
