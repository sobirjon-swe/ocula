import { test } from 'node:test'
import assert from 'node:assert/strict'
import {
    cents,
    decimal,
    cartTotal,
    money,
} from '../src/features/workspace/money.ts'
test('money arithmetic is exact beyond JavaScript safe integer precision', () => {
    assert.equal(
        cartTotal(
            [{ price: '9999999999999.99', quantity: 3 }],
            '.01'.replace(/^\./, '0.'),
        ),
        '29999999999999.96',
    )
    assert.equal(cartTotal([{ price: '0.10', quantity: 3 }], '0.20'), '0.10')
})
test('money never silently rounds malformed decimal input', () => {
    assert.throws(() => cents('1.001'))
    assert.throws(() => cents('Infinity'))
    assert.equal(decimal(cents('-0.01')), '-0.01')
})
test('money formatting follows shop format without floating point conversion', () => {
    assert.equal(money('1250000.00'), '1 250 000 so‘m')
    assert.equal(money('1250000.50'), '1 250 000.50 so‘m')
    assert.equal(money(null), '—')
})
test('checkout rounding matches configured half-up whole-som step', async () => {
    const { roundAmount } = await import('../src/features/workspace/money.ts')
    assert.equal(roundAmount('125049.99', '100'), '125000.00')
    assert.equal(roundAmount('125050.00', '100'), '125100.00')
    assert.equal(roundAmount('99.99', '1'), '100.00')
    assert.equal(roundAmount('124.99', '10'), '120.00')
})
