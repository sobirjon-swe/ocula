import assert from 'node:assert/strict'
import { test } from 'node:test'
import { createOutbox } from '../src/lib/offline/queue.ts'

function memory() {
    const values = new Map()
    return {
        getItem: (key) => values.get(key) ?? null,
        setItem: (key, value) => values.set(key, value),
        removeItem: (key) => values.delete(key),
    }
}

test('network retries keep the original key and survive a reload', async () => {
    const storage = memory()
    let box = createOutbox(storage, 7)
    const op = box.enqueue('/trip-stops/2/deliver', { lat: '41', lng: '69' })
    await box.flush(
        () => 7,
        async () => {
            throw { status: 0, message: 'offline' }
        },
    )
    box = createOutbox(storage, 7)
    assert.equal(box.list().length, 1)
    const sent = []
    await box.flush(
        () => 7,
        async (value) => {
            sent.push(value)
        },
    )
    assert.equal(sent[0].key, op.key)
    assert.equal(box.list().length, 0)
})

test('account switching cannot send another driver’s cached operations', async () => {
    const storage = memory()
    const box = createOutbox(storage, 7)
    box.enqueue('/trip-stops/2/deliver', {})
    let sent = 0
    await box.flush(
        () => 8,
        async () => {
            sent++
        },
    )
    assert.equal(sent, 0)
    assert.equal(createOutbox(storage, 8).list().length, 0)
    assert.equal(box.list().length, 1)
})

test('a conflict blocks later operations until the driver reconciles it', async () => {
    const box = createOutbox(memory(), 7)
    const first = box.enqueue('/trip-stops/2/deliver', {})
    box.enqueue('/trips/1/finish', {})
    let sent = 0
    await box.flush(
        () => 7,
        async () => {
            sent++
            throw { status: 409, message: 'Changed' }
        },
    )
    assert.equal(sent, 1)
    assert.equal(box.list()[0].error, 'Changed')
    await box.flush(
        () => 7,
        async () => {
            sent++
        },
    )
    assert.equal(sent, 1)
    box.discard(first.key)
    await box.flush(
        () => 7,
        async () => {
            sent++
        },
    )
    assert.equal(box.list().length, 0)
})

test('concurrent flush calls cannot dispatch the same operation twice', async () => {
    const box = createOutbox(memory(), 7)
    box.enqueue('/trips/1/start', {})
    let sent = 0
    await Promise.all([
        box.flush(
            () => 7,
            async () => {
                sent++
            },
        ),
        box.flush(
            () => 7,
            async () => {
                sent++
            },
        ),
    ])
    assert.equal(sent, 1)
})
