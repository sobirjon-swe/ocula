import { test, expect, type Page } from '@playwright/test'
const permissions = [
    'analytics.dashboard.view',
    'sales.order.create',
    'sales.order.view_any',
    'sales.payment.create',
    'sales.order.deliver',
    'sales.customer.view_any',
    'sales.customer.create',
    'sales.customer.debt.view',
    'clinic.visit.view_any',
    'clinic.visit.create',
    'clinic.visit.start',
    'clinic.prescription.create',
    'clinic.prescription.view_history',
    'workshop.work_order.view_any',
    'workshop.work_order.start',
    'workshop.work_order.finish',
    'warehouse.inventory.view_any',
    'warehouse.inventory.start',
    'warehouse.inventory.count',
    'warehouse.inventory.review',
]
const user = {
    id: 1,
    name: 'Test Seller',
    phone: '+998901234567',
    branch_id: 1,
    branch: { id: 1, name: 'A MAIN' },
    locale: 'en',
}
async function setup(page: Page, grants = permissions) {
    await page.addInitScript(
        ({ user, grants }) =>
            localStorage.setItem(
                'optika.session',
                JSON.stringify({
                    state: {
                        token: 'test-token',
                        user,
                        permissions: grants,
                        locale: 'en',
                    },
                    version: 0,
                }),
            ),
        { user, grants },
    )
    await page.route('**/api/v1/**', async (route) => {
        const url = new URL(route.request().url()),
            path = url.pathname.replace('/api/v1', '')
        let body: unknown = { data: [] }
        if (path === '/auth/me')
            body = { data: user, meta: { permissions: grants } }
        if (path === '/catalog-variants')
            body = {
                data: [
                    {
                        id: 5,
                        sku: 'RAY-1',
                        barcode: '12345',
                        product: { id: 2, name: 'Ray Ban' },
                        current_price: '125000.00',
                        branch_balances: [
                            { code: 'A', quantity: 3 },
                            { code: 'B', quantity: 1 },
                        ],
                    },
                ],
            }
        if (path === '/variants/5/prices/current')
            body = { data: { price: '125000.00' } }
        if (path === '/variants/by-barcode/12345')
            body = {
                data: { id: 5, sku: 'RAY-1', product: { name: 'Ray Ban' } },
            }
        if (path === '/customers')
            body = { data: [{ id: 7, name: 'Ali', phone: '+998901112233' }] }
        if (path === '/customers/7')
            body = { data: { id: 7, name: 'Ali', debt_balance: '50000.00' } }
        if (path === '/branches') body = { data: [{ id: 1, name: 'A MAIN' }] }
        if (path === '/visits')
            body = {
                data: [
                    {
                        id: 8,
                        queue_number: 1,
                        customer_id: 7,
                        branch_id: 1,
                        customer: { name: 'Ali' },
                        status: 'waiting',
                    },
                ],
                meta: { last_page: 1 },
            }
        if (path === '/visits/8')
            body = {
                data: {
                    id: 8,
                    customer_id: 7,
                    branch_id: 1,
                    customer: { name: 'Ali' },
                    status: 'waiting',
                },
            }
        if (path === '/customers/7/prescriptions')
            body = {
                data: [
                    {
                        id: 9,
                        od: {
                            sph: '-2.00',
                            cyl: '-0.50',
                            axis: 90,
                            add: '0.00',
                        },
                        os: {
                            sph: '-1.00',
                            cyl: '0.00',
                            axis: 90,
                            add: '0.00',
                        },
                        pd: '62.00',
                    },
                ],
            }
        if (path === '/work-orders')
            body = {
                data: [
                    {
                        id: 10,
                        order_id: 3,
                        order_number: 'A-3',
                        customer_name: 'Ali',
                        status: 'queued',
                        priority: 'normal',
                        due_at: '2026-10-30T09:00:00Z',
                        prescription: {
                            od: { sph: '-2.00', cyl: '-0.50', axis: 90 },
                            os: { sph: '-1.00', cyl: '0.00', axis: 90 },
                        },
                    },
                ],
                meta: { last_page: 1 },
            }
        if (path === '/inventories')
            body = {
                data: [
                    {
                        id: 11,
                        number: 'INV-11',
                        status: 'counting',
                        location_id: 1,
                    },
                ],
                last_page: 1,
            }
        if (path === '/inventories/11')
            body = {
                data: {
                    id: 11,
                    number: 'INV-11',
                    status: 'counting',
                    items: [
                        {
                            id: 12,
                            variant_id: 5,
                            expected_qty: 3,
                            actual_qty: null,
                            unit_cost: '50000.00',
                        },
                    ],
                },
            }
        if (path === '/checkout')
            body = {
                data: {
                    id: 3,
                    number: 'A-3',
                    total: '125000.00',
                    paid: '125000.00',
                    debt: '0.00',
                    status: 'closed',
                    payment_status: 'paid',
                },
            }
        if (path === '/orders/3')
            body = {
                data: {
                    id: 3,
                    number: 'A-3',
                    total: '125000.00',
                    paid: '125000.00',
                    debt: '0.00',
                    status: 'closed',
                    payment_status: 'paid',
                    items: [],
                },
            }
        await route.fulfill({
            status: 200,
            contentType: 'application/json',
            body: JSON.stringify(body),
        })
    })
}
test('POS shows stock, customer debt and posts atomic split payment checkout', async ({
    page,
}) => {
    await setup(page)
    await page.goto('/pos')
    await expect(page.getByText('A:3 · B:1')).toBeVisible()
    await page.getByRole('button', { name: 'Add', exact: true }).first().click()
    await page.getByLabel('Customer · name or phone').fill('Ali')
    await page.getByRole('button', { name: 'Ali · +998901112233' }).click()
    await expect(page.getByText('Customer debt: 50000.00')).toBeVisible()
    await page.keyboard.press('F2')
    await expect(page.locator('#checkout-amount')).toBeFocused()
    await page.locator('#checkout-amount').fill('125000.00')
    await page.getByRole('button', { name: 'Add', exact: true }).last().click()
    const request = page.waitForRequest(
        (r) => r.url().endsWith('/checkout') && r.method() === 'POST',
    )
    await page.getByRole('button', { name: 'Create sale', exact: true }).click()
    const body = (await request).postDataJSON()
    expect(body.payments).toEqual([{ amount: '125000.00', method: 'cash' }])
    expect(body.items).toEqual([{ kind: 'variant', id: 5, quantity: 1 }])
    expect(body.customer_id).toBe(7)
    expect((await request).headers()['idempotency-key']).toBeTruthy()
    await expect(
        page.getByRole('heading', { name: /Order created/ }),
    ).toBeVisible()
})
test('clinic copies nested OD/OS history and warns on 1.5 change', async ({
    page,
}) => {
    await setup(page)
    await page.goto('/work/visits')
    await page.getByRole('button', { name: 'Open', exact: true }).click()
    await page
        .getByText('Write prescription · OD / OS', { exact: true })
        .click()
    await page
        .getByRole('button', { name: 'Copy previous prescription' })
        .click()
    await expect(page.getByLabel('OD SPH', { exact: true })).toHaveValue(
        '-2.00',
    )
    await page.getByLabel('OD SPH', { exact: true }).selectOption('-4.00')
    await expect(page.getByRole('alert')).toContainText('changed by 1.5')
    const request = page.waitForRequest(
        (r) => r.url().endsWith('/prescriptions') && r.method() === 'POST',
    )
    await page.getByRole('button', { name: 'Save', exact: true }).click()
    const body = (await request).postDataJSON()
    expect(body.od_sph).toBe('-4.00')
    expect(body.os_sph).toBe('-1.00')
    expect(body.visit_id).toBe(8)
})
test('inventory never turns an untouched count into zero', async ({ page }) => {
    await setup(page)
    await page.goto('/work/inventories')
    await page.getByRole('button', { name: 'Open', exact: true }).click()
    await expect(
        page.getByLabel('Counted quantity', { exact: true }),
    ).toHaveValue('')
    await page.getByLabel('Counted quantity', { exact: true }).fill('0')
    const request = page.waitForRequest(
        (r) => r.url().endsWith('/counts') && r.method() === 'PUT',
    )
    await page.getByRole('button', { name: 'Save counts' }).click()
    expect((await request).postDataJSON().items[0].actual_qty).toBe(0)
})
test('mobile navigation opens and derived Cyrillic is available', async ({
    page,
}) => {
    await page.setViewportSize({ width: 390, height: 844 })
    await setup(page)
    await page.goto('/pos')
    await page.getByRole('button', { name: 'Open menu' }).click()
    await expect(page.getByRole('navigation')).toBeVisible()
    await page.getByRole('button', { name: 'Close menu' }).click()
    await page.getByLabel('Language', { exact: true }).selectOption('uz-cyrl')
    await expect(
        page.getByRole('heading', { name: 'Савдо', exact: true }),
    ).toBeVisible()
    expect(
        await page.evaluate(
            () => document.documentElement.scrollWidth <= window.innerWidth,
        ),
    ).toBeTruthy()
})
test('direct restricted route cannot reveal POS', async ({ page }) => {
    await setup(page, ['analytics.dashboard.view'])
    await page.goto('/pos')
    await expect(page).toHaveURL(/\/reports$/)
    await expect(
        page.getByRole('heading', { name: 'Point of sale' }),
    ).toHaveCount(0)
})

test('desktop POS and mobile clinic/workshop have usable rendered layouts', async ({
    page,
}) => {
    await setup(page)
    await page.setViewportSize({ width: 1440, height: 1000 })
    await page.goto('/pos')
    await expect(page.getByText('A:3 · B:1')).toBeVisible()
    await page.screenshot({
        path: 'test-results/pos-desktop.png',
        fullPage: true,
    })
    await page.setViewportSize({ width: 390, height: 844 })
    await page.goto('/work/visits')
    await page.getByRole('button', { name: 'Open', exact: true }).click()
    await page
        .getByText('Write prescription · OD / OS', { exact: true })
        .click()
    await page.screenshot({
        path: 'test-results/clinic-mobile.png',
        fullPage: true,
    })
    expect(
        await page.evaluate(
            () => document.documentElement.scrollWidth <= window.innerWidth,
        ),
    ).toBeTruthy()
    await page.goto('/work/work-orders')
    await expect(
        page.getByRole('button', { name: 'Start', exact: true }),
    ).toBeVisible()
    await page.screenshot({
        path: 'test-results/workshop-mobile.png',
        fullPage: true,
    })
    expect(
        await page.evaluate(
            () => document.documentElement.scrollWidth <= window.innerWidth,
        ),
    ).toBeTruthy()
})

test('failed and zero-stock searches automatically record lost sales once', async ({
    page,
}) => {
    await setup(page, [...permissions, 'warehouse.lost_sale.create'])
    await page.route('**/api/v1/catalog-variants**', (route) =>
        route.fulfill({
            contentType: 'application/json',
            body: JSON.stringify({ data: [] }),
        }),
    )
    let count = 0
    page.on('request', (r) => {
        if (r.url().endsWith('/lost-sales') && r.method() === 'POST') count++
    })
    await page.goto('/pos')
    const recorded = page.waitForRequest(
        (r) => r.url().endsWith('/lost-sales') && r.method() === 'POST',
    )
    await page.getByLabel('Product name or barcode').fill('Missing Hoya')
    const request = await recorded
    expect(request.postDataJSON()).toMatchObject({
        branch_id: 1,
        search_term: 'Missing Hoya',
        reason: 'not_in_catalog',
    })
    await page.getByLabel('Product name or barcode').fill('')
    await page.waitForTimeout(350)
    await page.getByLabel('Product name or barcode').fill('Missing Hoya')
    await page.waitForTimeout(500)
    expect(count).toBe(1)
    await page.route('**/api/v1/catalog-variants**', (route) =>
        route.fulfill({
            contentType: 'application/json',
            body: JSON.stringify({
                data: [
                    {
                        id: 55,
                        sku: 'HOYA',
                        product: { name: 'Hoya' },
                        current_price: '100000',
                        branch_balances: [
                            { branch_id: 1, code: 'A', quantity: 0 },
                            { branch_id: 2, code: 'B', quantity: 3 },
                        ],
                    },
                ],
            }),
        }),
    )
    const out = page.waitForRequest(
        (r) => r.url().endsWith('/lost-sales') && r.method() === 'POST',
    )
    await page.getByLabel('Product name or barcode').fill('Hoya zero')
    expect((await out).postDataJSON()).toMatchObject({
        variant_id: 55,
        reason: 'out_of_stock',
    })
})
test('custom lens stores manual cost and optical parameters on its service line', async ({
    page,
}) => {
    await setup(page)
    await page.route('**/api/v1/services**', (route) =>
        route.fulfill({
            contentType: 'application/json',
            body: JSON.stringify({
                data: [
                    { id: 99, name: 'Custom lens service', price: '200000.00' },
                ],
            }),
        }),
    )
    await page.goto('/pos')
    await page.getByText('Custom lens order', { exact: true }).click()
    const details = page
        .locator('details')
        .filter({ has: page.getByText('Custom lens order', { exact: true }) })
    await details
        .getByRole('combobox', { name: 'Lens service *', exact: true })
        .selectOption('99')
    await details.getByLabel('Total cost *', { exact: true }).fill('100000.50')
    await details
        .getByRole('combobox', { name: 'OD SPH', exact: true })
        .selectOption('-2.00')
    await details
        .getByRole('button', { name: 'Add to cart', exact: true })
        .click()
    await expect(page.getByLabel('Quantity', { exact: true })).toHaveValue('1')
    await page
        .getByRole('combobox', { name: 'Sale type', exact: true })
        .selectOption('order')
    const order = page.waitForRequest(
        (r) => r.url().endsWith('/orders') && r.method() === 'POST',
    )
    await page.getByRole('button', { name: 'Create sale', exact: true }).click()
    expect((await order).postDataJSON().items[0]).toMatchObject({
        kind: 'service',
        id: 99,
        cost_total: '100000.50',
        custom_lens_params: { od_sph: '-2.00' },
    })
})

test('workshop records a defect by material and reason without a note dialog', async ({
    page,
}) => {
    await setup(page, [...permissions, 'workshop.work_order.defect'])
    await page.route('**/api/v1/work-orders?**', (route) =>
        route.fulfill({
            contentType: 'application/json',
            body: JSON.stringify({
                data: [
                    {
                        id: 10,
                        order_id: 3,
                        order_number: 'A-3',
                        customer_name: 'Ali',
                        status: 'queued',
                        priority: 'normal',
                        items: [
                            {
                                id: 40,
                                variant_id: 5,
                                quantity: 1,
                                variant: {
                                    sku: 'LENS-1',
                                    product: { name: 'Hoya' },
                                },
                            },
                        ],
                    },
                ],
            }),
        }),
    )
    await page.goto('/work/work-orders')
    await page.getByRole('button', { name: 'Defect', exact: true }).click()
    const request = page.waitForRequest(
        (r) =>
            r.url().endsWith('/work-orders/10/defect') && r.method() === 'POST',
    )
    await page
        .getByRole('button', { name: 'Master error', exact: true })
        .click()
    expect((await request).postDataJSON()).toMatchObject({
        item_id: 40,
        reason: 'master_error',
        quantity: 1,
    })
    expect((await request).postDataJSON().note).toContain('Master error')
})

test('Enter adds a current name or SKU result without requiring a barcode',async({page})=>{await setup(page);await page.goto('/pos');await page.getByLabel('Product name or barcode').fill('Ray Ban');await page.getByLabel('Product name or barcode').press('Enter');await expect(page.getByLabel('Quantity',{exact:true})).toHaveValue('1');await expect(page.locator('aside').last().getByText('Ray Ban · RAY-1',{exact:true})).toBeVisible()})
