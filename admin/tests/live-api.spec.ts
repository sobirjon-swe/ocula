import { readFile } from 'node:fs/promises'
import { test, expect, type Page } from '@playwright/test'
test.describe('live API', () => {
    test.skip(
        !process.env.OPTIKA_LIVE,
        'Requires seeded optika_preview on ports 5175/8010',
    )
    test.setTimeout(90000)
    async function login(page: Page, phone: string) {
        await page.goto('http://127.0.0.1:5175/login')
        await page.locator('input[type=tel]').fill(phone)
        await page.locator('input[type=password]').fill('password')
        await page.locator('form button[type=submit]').click()
        await expect(page.locator('header select')).toBeVisible()
        await page.locator('header select').selectOption('en')
        await expect(
            page.getByRole('button', { name: 'Sign out' }),
        ).toBeVisible()
    }
    test('seller scans stock and completes a fully paid quick sale against Laravel', async ({
        page,
    }) => {
        await login(page, '+998901000011')
        await page.goto('http://127.0.0.1:5175/pos')
        await page.getByLabel('Product name or barcode').fill('20260001')
        await page.getByLabel('Product name or barcode').press('Enter')
        await expect(page.getByLabel('Quantity', { exact: true })).toHaveValue(
            '1',
        )
        const total = await page
            .locator('aside')
            .last()
            .getByText(/so‘m/)
            .last()
            .innerText()
        await page
            .locator('#checkout-amount')
            .fill(total.replace(/[^0-9.]/g, ''))
        await page
            .locator('aside')
            .last()
            .getByRole('button', { name: 'Add', exact: true })
            .click()
        const response = page.waitForResponse(
            (r) =>
                r.url().endsWith('/checkout') &&
                r.request().method() === 'POST',
        )
        await page
            .getByRole('button', { name: 'Create sale', exact: true })
            .click()
        const result = await response
        expect(result.status(), await result.text()).toBe(201)
        const body = await result.json()
        expect(body.data.payment_status).toBe('paid')
        expect(body.data.status).toBe('closed')
        await expect(
            page.getByRole('heading', { name: /Order created/ }),
        ).toBeVisible()
        await page.screenshot({
            path: 'test-results/live-pos.png',
            fullPage: true,
        })
    })
    test('doctor creates a visit, saves OD OS and validates axis against Laravel', async ({
        page,
    }) => {
        await login(page, '+998901000003')
        await page.goto('http://127.0.0.1:5175/work/visits')
        await page.getByRole('button', { name: 'Create', exact: true }).click()
        const customer = page.locator('form select').nth(1)
        await expect(customer.locator('option')).not.toHaveCount(1)
        const option = await customer
            .locator('option')
            .filter({ hasText: 'Demo mijoz' })
            .getAttribute('value')
        expect(option).toBeTruthy()
        await customer.selectOption(option!)
        const response = page.waitForResponse(
            (r) =>
                r.url().endsWith('/visits') && r.request().method() === 'POST',
        )
        await page.getByRole('button', { name: 'Save', exact: true }).click()
        const result = await response
        if (result.status() === 422) {
            expect(await result.text()).toContain('already in')
            await page
                .getByRole('button', { name: 'Close', exact: true })
                .click()
        } else expect(result.status(), await result.text()).toBe(201)
        await page
            .getByRole('button', { name: 'Open', exact: true })
            .last()
            .click()
        await page
            .getByText('Write prescription · OD / OS', { exact: true })
            .click()
        await page.getByLabel('OD SPH', { exact: true }).selectOption('-2.00')
        await page.getByLabel('OS SPH', { exact: true }).selectOption('-1.50')
        await page.getByLabel('OD AXIS', { exact: true }).fill('181')
        await page.getByRole('button', { name: 'Save', exact: true }).click()
        expect(
            await page
                .getByLabel('OD AXIS', { exact: true })
                .evaluate((e: HTMLInputElement) => e.validity.rangeOverflow),
        ).toBeTruthy()
        await page.getByLabel('OD AXIS', { exact: true }).fill('90')
        const prescription = page.waitForResponse(
            (r) =>
                r.url().endsWith('/prescriptions') &&
                r.request().method() === 'POST',
        )
        await page.getByRole('button', { name: 'Save', exact: true }).click()
        const saved = await prescription
        expect(saved.status(), await saved.text()).toBe(201)
        const body = await saved.json()
        expect(body.data.od.sph).toBe('-2.00')
        expect(body.data.ticket_active).toBe(true)
        await page.screenshot({
            path: 'test-results/live-clinic.png',
            fullPage: true,
        })
    })
    test('seller quick-add explicitly receives counted stock then adds the variant to cart', async ({
        page,
    }) => {
        await login(page, '+998901000011')
        await page.goto('http://127.0.0.1:5175/pos')
        await page.getByText('Quick-add product', { exact: true }).click()
        const details = page.locator('details').filter({
            has: page.getByText('Quick-add product', { exact: true }),
        })
        await details
            .getByLabel('Name *', { exact: true })
            .fill('Live counted frame ' + Date.now())
        await details
            .getByRole('combobox', { name: 'Type *', exact: true })
            .selectOption('frame')
        await details.getByLabel('Price *', { exact: true }).fill('125000')
        await details
            .getByLabel('Physically counted quantity *', { exact: true })
            .fill('2')
        await details.getByLabel('Unit cost *', { exact: true }).fill('50000')
        const response = page.waitForResponse(
            (r) =>
                r.url().endsWith('/products/quick') &&
                r.request().method() === 'POST',
        )
        await details.getByRole('button', { name: 'Save', exact: true }).click()
        const result = await response
        expect(result.status(), await result.text()).toBe(201)
        expect((await result.json()).data.status).toBe('pending')
        await expect(page.getByLabel('Quantity', { exact: true })).toHaveValue(
            '1',
        )
        await expect(
            page
                .locator('aside')
                .last()
                .getByText(/Live counted frame/),
        ).toBeVisible()
    })

    test('director navigation has no broken API list endpoints', async ({
        page,
    }) => {
        const failures: string[] = []
        page.on('response', (r) => {
            if (r.url().includes('/api/v1/') && r.status() >= 400)
                failures.push(r.status() + ' ' + r.url())
        })
        await login(page, '+998901000001')
        const links = await page
            .getByRole('navigation')
            .getByRole('link')
            .evaluateAll((nodes) =>
                nodes.map((n) => n.getAttribute('href')).filter(Boolean),
            )
        for (const href of links) {
            await page.goto('http://127.0.0.1:5175' + href)
            await page.waitForLoadState('networkidle')
            await expect(page.locator('main h1')).toBeVisible()
        }
        expect(failures).toEqual([])
    })

    test('all operational reports load and XLSX downloads as a real workbook', async ({
        page,
    }, info) => {
        await login(page, '+998901000001')
        await page.goto('http://127.0.0.1:5175/reports')
        for (const report of [
            'Debt aging',
            'Stock turnover',
            'Abandoned orders',
            'Transport costs',
            'Supplier balances',
            'Cash report',
            'Profit report',
            'ABC analysis',
            'Dead stock',
            'Lost sales',
            'Staff',
            'Branch rating',
        ]) {
            const result = page.waitForResponse(
                (r) =>
                    r.url().includes('/analytics/') &&
                    !r.url().includes('/exports/') &&
                    r.request().method() === 'GET',
            )
            await page
                .getByRole('button', { name: report, exact: true })
                .click()
            const response = await result
            expect(response.status(), await response.text()).toBe(200)
        }
        await page
            .getByRole('button', { name: 'Debt aging', exact: true })
            .click()
        await page.waitForLoadState('networkidle')
        const downloaded = page.waitForEvent('download')
        await page.getByRole('button', { name: 'XLSX', exact: true }).click()
        const file = await downloaded
        expect(await file.failure()).toBeNull()
        const path = info.outputPath('debt-aging.xlsx')
        await file.saveAs(path)
        expect((await readFile(path)).subarray(0, 2).toString()).toBe('PK')
    })
})
