export function cents(value: string): bigint {
    if (!/^-?\d+(?:\.\d{1,2})?$/.test(value))
        throw new Error('Invalid decimal amount')
    const negative = value.startsWith('-')
    const [whole, fraction = ''] = value.replace('-', '').split('.')
    const amount = BigInt(whole) * 100n + BigInt(fraction.padEnd(2, '0'))
    return negative ? -amount : amount
}
export function decimal(value: bigint): string {
    const sign = value < 0n ? '-' : ''
    const absolute = value < 0n ? -value : value
    return (
        sign + absolute / 100n + '.' + String(absolute % 100n).padStart(2, '0')
    )
}
export function cartTotal(
    items: { price: string; quantity: number }[],
    discount = '0',
): string {
    return decimal(
        items.reduce(
            (sum, item) => sum + cents(item.price) * BigInt(item.quantity),
            0n,
        ) - cents(discount || '0'),
    )
}
export function money(value: unknown): string {
    if (value === null || value === undefined) return '—'
    try {
        const amount = cents(String(value)),
            negative = amount < 0n,
            absolute = negative ? -amount : amount,
            whole = String(absolute / 100n).replace(
                /\B(?=(\d{3})+(?!\d))/g,
                ' ',
            ),
            fraction = absolute % 100n
        return (
            (negative ? '-' : '') +
            whole +
            (fraction ? '.' + String(fraction).padStart(2, '0') : '') +
            ' so‘m'
        )
    } catch {
        return String(value)
    }
}
export function roundAmount(value: string, stepSom: string): string {
    const amount = cents(value),
        step = cents(stepSom)
    if (step <= 0n) throw new Error('Rounding step must be positive')
    const sign = amount < 0n ? -1n : 1n,
        absolute = amount < 0n ? -amount : amount
    return decimal(((absolute + step / 2n) / step) * step * sign)
}
