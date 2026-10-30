import { test, expect } from '@playwright/test'
test('production PWA cold reload keeps route and balance, queues GPS delivery and replays its original key', async ({
    page,
    context,
}) => {
    const runtimeErrors:string[]=[]
    page.on('pageerror',error=>runtimeErrors.push(error.message))
    let online = true,
        delivered = false
    const sentKeys: string[] = []
    const permissions = [
        'delivery.trip.view_any',
        'delivery.trip.start',
        'delivery.trip.finish',
        'delivery.stop.deliver',
        'delivery.stop.fail',
        'delivery.balance.view',
        'analytics.dashboard.view',
        'payroll.bonus.view_own',
    ]
    const user = {
        id: 5,
        name: 'Driver Test',
        phone: '+998901000005',
        branch_id: 1,
        branch: { id: 1, name: 'A MAIN' },
        locale: 'en',
    }
    await page.addInitScript(
        ({ user, permissions }) => {
            if (!localStorage.getItem('optika.session'))
                localStorage.setItem(
                    'optika.session',
                    JSON.stringify({
                        state: {
                            token: 'pwa-test-token',
                            user,
                            permissions,
                            locale: 'en',
                        },
                        version: 0,
                    }),
                )
        },
        { user, permissions },
    )
    await context.route('**/api/v1/**', async (route) => {
        if (!online) {
            await route.abort('internetdisconnected')
            return
        }
        const path = new URL(route.request().url()).pathname
        let body: unknown = { data: [] }
        if (path.endsWith('/auth/me'))
            body = { data: user, meta: { permissions } }
        if (path.endsWith('/trips'))
            body = {
                data: [
                    {
                        id: 7,
                        driver_id: 5,
                        date: '2026-10-30',
                        status: 'in_progress',
                        stops: [
                            {
                                id: 9,
                                sequence: 1,
                                type: 'customer',
                                status: delivered ? 'delivered' : 'pending',
                                address: 'Tashkent, test street',
                                lat: '41.3',
                                lng: '69.2',
                                cash_to_collect: '100000.00',
                                contact_name: 'Ali Customer',
                                contact_phone: '+998901112233',
                                order_id: 3,
                                transfer_id: null,
                            },
                        ],
                    },
                ],
            }
        if (path.endsWith('/driver-balances/5'))
            body = {
                data: {
                    cash_amount: delivered ? '150000.00' : '50000.00',
                    goods_quantity: delivered ? 0 : 1,
                },
            }
        if (path.endsWith('/trip-stops/9/deliver')) {
            sentKeys.push(route.request().headers()['idempotency-key'])
            expect(route.request().postDataJSON()).toMatchObject({
                lat: '41.3',
                lng: '69.2',
            })
            if (sentKeys.length === 1) {
                await route.fulfill({
                    status: 503,
                    contentType: 'application/json',
                    body: JSON.stringify({ message: 'Temporary failure' }),
                })
                return
            }
            delivered = true
            body = { data: { id: 9, status: 'delivered' } }
        }
        await route.fulfill({
            status: 200,
            contentType: 'application/json',
            body: JSON.stringify(body),
        })
    })
    await page.goto('/delivery')
    await expect(page.getByText('Ali Customer', { exact: false })).toBeVisible()
    await expect(page.getByText(/Cash with me:.*50/)).toBeVisible()
    const cached = await page.evaluate(async () => {
        await navigator.serviceWorker.ready
        if (!navigator.serviceWorker.controller)
            await new Promise<void>((resolve) =>
                navigator.serviceWorker.addEventListener(
                    'controllerchange',
                    () => resolve(),
                    { once: true },
                ),
            )
        const cache = await caches.open('optika-driver-shell-v3')
        return (await cache.keys()).map(
            (request) => new URL(request.url).pathname,
        )
    })
    expect(
        cached.filter((path) => path.startsWith('/assets/')).length,
    ).toBeGreaterThanOrEqual(5)
    expect(cached.some((path) => path.startsWith('/api/'))).toBe(false)
    online = false
    await context.setOffline(true)
    await page.reload()
    await expect(page.getByText('Ali Customer', { exact: false })).toBeVisible()
    await expect(page.getByText(/Offline —/)).toBeVisible()
    await expect(page.getByText(/Cash with me:.*50/)).toBeVisible()
    await page.getByRole('button', { name: 'Delivered', exact: true }).click()
    await expect(page.getByText('1 operations waiting to send')).toBeVisible()
    const operation = await page.evaluate(
        () =>
            JSON.parse(
                localStorage.getItem('optika.driver.outbox.5') ?? '[]',
            )[0],
    )
    expect(operation.key).toBeTruthy()
    await page.reload()
    await expect(page.getByText('1 operations waiting to send')).toBeVisible()
    await expect(
        page.getByRole('button', { name: 'Delivered', exact: true }),
    ).toHaveCount(0)
    online = true
    await context.setOffline(false)
    await expect.poll(() => sentKeys.length).toBe(1)
    expect(
        await page.evaluate(
            () =>
                JSON.parse(
                    localStorage.getItem('optika.driver.outbox.5') ?? '[]',
                )[0].key,
        ),
    ).toBe(operation.key)
    await page.getByRole('button', { name: 'Refresh', exact: true }).click()
    await expect.poll(() => sentKeys.length).toBe(2)
    await expect(page.getByText('0 operations waiting to send')).toBeVisible()
    expect(sentKeys).toEqual([operation.key, operation.key])
    await expect(page.getByText(/Cash with me:.*150/)).toBeVisible()
    expect(runtimeErrors).toEqual([])
    await page.screenshot({
        path: 'test-results/driver-production-pwa.png',
        fullPage: true,
    })
})
