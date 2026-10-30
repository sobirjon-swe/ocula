import { useEffect, useRef, useState } from 'react'
import { useMutation } from '@tanstack/react-query'
import { api, idempotencyKey } from '@/lib/api/client'
import { useSession } from '@/lib/session'
import { useWords } from './copy'
import {
    Skeleton,
    DomainForm,
    ErrorNotice,
    f,
    rows,
    select,
    textValue,
    useList,
    type Row,
} from './shared'
import { useAutomaticLostSale } from './useAutomaticLostSale'
import { CustomLens } from './CustomLens'
import { cartTotal, money, roundAmount } from './money'
import { QuickProduct } from './QuickProduct'
import { ResourceCreate } from './ResourcePage'
import { resources } from './resources'
import { ResourceDetails } from './ResourcePage'
type CartItem = {
    id: number
    kind: 'variant' | 'service'
    name: string
    quantity: number
    price: string
    cost_total?: string
    custom_lens_params?: Record<string, unknown>
}
export function PosPage() {
    const w = useWords(),
        user = useSession((s) => s.user),
        permissions = useSession((s) => s.permissions),
        input = useRef<HTMLInputElement>(null)
    const [search, setSearch] = useState(''),
        [cart, setCart] = useState<CartItem[]>([]),
        [customer, setCustomer] = useState(''),
        [type, setType] = useState('quick'),
        [discount, setDiscount] = useState('0'),
        [order, setOrder] = useState<Row | null>(null),
        [barcodeError, setBarcodeError] = useState<unknown>(null)
    const [debounced, setDebounced] = useState('')
    useEffect(() => {
        const timer = setTimeout(() => setDebounced(search), 250)
        return () => clearTimeout(timer)
    }, [search])
    const posConfig = useList('/pos-config')
    const products = useList('/catalog-variants', {
            search: debounced,
            per_page: 30,
            branch_id: user?.branch_id,
        }),
        customers = useList(
            '/customers',
            {
                filter: {
                    [/^\+?\d/.test(customer) ? 'phone' : 'name']: customer,
                },
                per_page: 8,
            },
            customer.length >= 2,
        )
    const [customerId, setCustomerId] = useState<number | null>(null),
        [dueDate, setDueDate] = useState(''),
        [deliveryType, setDeliveryType] = useState('pickup'),
        [prescriptionId, setPrescriptionId] = useState('')
    const prescriptions = useList(
        '/prescriptions',
        { filter: { customer_id: customerId } },
        customerId !== null,
    )
    const automaticLostSaleError = useAutomaticLostSale(
        debounced,
        products.data,
        products.isSuccess,
        customerId,
    )
    const selectedCustomer = useList(
        '/customers/' + customerId,
        {},
        customerId !== null,
    )
    const [checkoutPayments, setCheckoutPayments] = useState<
        { amount: string; method: string }[]
    >([])
    const [checkoutAmount, setCheckoutAmount] = useState(''),
        [checkoutMethod, setCheckoutMethod] = useState('cash')
    const [payment, setPayment] = useState(''),
        [method, setMethod] = useState('cash'),
        [paymentKey, setPaymentKey] = useState(idempotencyKey),
        [orderKey, setOrderKey] = useState(idempotencyKey)
    const add = (item: CartItem) =>
        setCart((old) => {
            const found = old.find(
                (i) =>
                    i.id === item.id &&
                    i.kind === item.kind &&
                    !i.custom_lens_params &&
                    !item.custom_lens_params,
            )
            return found
                ? old.map((i) =>
                      i === found ? { ...i, quantity: i.quantity + 1 } : i,
                  )
                : [...old, item]
        })
    async function addVariant(variant: Row, name: string) {
        try {
            const price = await api.get<{ data: { price: string } }>(
                '/variants/' + variant.id + '/prices/current',
                { params: { branch_id: user?.branch_id } },
            )
            add({
                id: variant.id,
                kind: 'variant',
                name:
                    name +
                    ' · ' +
                    textValue(variant.sku ?? variant.optical_label),
                quantity: 1,
                price:
                    price.data?.price ?? String(variant.current_price ?? '0'),
            })
            setBarcodeError(null)
        } catch (error) {
            setBarcodeError(error)
        }
    }
    async function scan() {
        const term=search.trim()
        if(!term)return
        try {
            const matches=debounced===term&&products.isSuccess?rows(products.data):rows(await api.get('/catalog-variants',{params:{search:term,branch_id:user?.branch_id,per_page:30}}))
            const exact=matches.find(row=>String(row.sku??'').toLocaleLowerCase()===term.toLocaleLowerCase()||String(row.barcode??'')===term)
            const selected=exact??(!/^\d+$/.test(term)?matches[0]:undefined)
            if(selected){await addVariant(selected,textValue((selected.product as Row)?.name));setSearch('');return}

            const result = await api.get<{ data: Row }>(
                '/variants/by-barcode/' + encodeURIComponent(search),
            )
            await addVariant(
                result.data,
                textValue((result.data.product as Row)?.name),
            )
            setSearch('')
        } catch (error) {
            setBarcodeError(error)
        }
    }
    const create = useMutation({
        mutationFn: () =>
            api.post<{ data: Row }>(
                type === 'quick' ? '/checkout' : '/orders',
                {
                    idempotencyKey: orderKey,
                    body: {
                        branch_id: user?.branch_id,
                        customer_id: customerId,
                        type,
                        discount,
                        ...(dueDate ? { due_date: dueDate } : {}),
                        delivery_type: deliveryType,
                        ...(prescriptionId
                            ? { prescription_id: Number(prescriptionId) }
                            : {}),
                        ...(type === 'quick'
                            ? { payments: checkoutPayments }
                            : {}),
                        items: cart.map((i) => ({
                            kind: i.kind,
                            id: i.id,
                            quantity: i.quantity,
                            ...(i.cost_total
                                ? {
                                      cost_total: i.cost_total,
                                      custom_lens_params: i.custom_lens_params,
                                  }
                                : {}),
                        })),
                    },
                },
            ),
        onSuccess: (result) => {
            setOrder(result.data)
            setCart([])
            setCheckoutPayments([])
            setOrderKey(idempotencyKey())
        },
    })
    const pay = useMutation({
        mutationFn: () =>
            api.post('/orders/' + order?.id + '/payments', {
                idempotencyKey: paymentKey,
                body: { amount: payment, method },
            }),
        onSuccess: () => {
            setPayment('')
            setPaymentKey(idempotencyKey())
        },
    })
    useEffect(() => {
        const listener = (event: KeyboardEvent) => {
            if (event.key === 'F2') {
                event.preventDefault()
                document.getElementById('checkout-amount')?.focus()
            }
            if (event.key === 'Escape') {
                event.preventDefault()
                setSearch('')
                input.current?.focus()
            }
            if (event.key === 'F8') {
                event.preventDefault()
                document.getElementById('pos-checkout')?.focus()
            }
        }
        window.addEventListener('keydown', listener)
        return () => window.removeEventListener('keydown', listener)
    }, [])
    const estimate = roundAmount(
        cartTotal(cart, discount),
        String(
            (posConfig.data as { data?: { money_rounding_step?: string } })
                ?.data?.money_rounding_step ?? '100',
        ),
    )
    return (
        <div className="space-y-5">
            <div className="flex flex-wrap items-baseline justify-between gap-3">
                <h1 className="text-2xl font-semibold">
                    {w('Savdo', 'Продажа', 'Point of sale')}
                </h1>
                <p className="text-sm text-neutral-500">
                    F2 · {w('To‘lov', 'Оплата', 'Payment')} / Esc ·{' '}
                    {w('Tozalash', 'Очистить', 'Clear')} / F8 ·{' '}
                    {w('Rasmiylashtirish', 'Оформить', 'Checkout')}
                </p>
            </div>
            {!user?.branch_id && (
                <p role="alert">
                    {w(
                        'Savdo uchun xodimga filial biriktiring.',
                        'Назначьте сотруднику филиал для продаж.',
                        'Assign a branch to the employee before selling.',
                    )}
                </p>
            )}
            <div className="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_400px]">
                <section className="space-y-4">
                    <form
                        onSubmit={(e) => {
                            e.preventDefault()
                            void scan()
                        }}
                        className="flex gap-2"
                    >
                        <input
                            ref={input}
                            autoFocus
                            aria-label={w(
                                'Tovar nomi yoki shtrix-kod',
                                'Название или штрихкод',
                                'Product name or barcode',
                            )}
                            placeholder={w(
                                'Nom / shtrix-kod · Enter bilan skanerlash',
                                'Название / штрихкод · Enter для сканирования',
                                'Name / barcode · Enter to scan',
                            )}
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            className="min-w-0 flex-1 rounded-lg border border-neutral-300 bg-white px-3 py-3"
                        />
                        <button className="action-secondary">
                            {w('Skanerlash', 'Сканировать', 'Scan')}
                        </button>
                    </form>
                    <ErrorNotice
                        error={
                            barcodeError ??
                            products.error ??
                            automaticLostSaleError
                        }
                    />
                    {products.isPending && <Skeleton />}
                    <div className="divide-y divide-neutral-200 rounded-lg border border-neutral-200 bg-white">
                        {rows(products.data).map((variant) => (
                            <div
                                key={variant.id}
                                className="flex flex-wrap items-center justify-between gap-3 p-4"
                            >
                                <div className="space-y-1">
                                    <div className="font-medium">
                                        {textValue(
                                            (variant.product as Row)?.name,
                                        )}{' '}
                                        · {textValue(variant.sku)}
                                    </div>
                                    <div className="text-sm text-neutral-600">
                                        {textValue(
                                            variant.optical_label ??
                                                variant.barcode,
                                        )}{' '}
                                        · {textValue(variant.current_price)} UZS
                                    </div>
                                    <p className="text-sm tabular-nums">
                                        {rows(variant.branch_balances)
                                            .map(
                                                (b) =>
                                                    textValue(b.code) +
                                                    ':' +
                                                    textValue(b.quantity),
                                            )
                                            .join(' · ')}
                                    </p>
                                </div>
                                <button
                                    className="action-secondary"
                                    onClick={() =>
                                        void addVariant(
                                            variant,
                                            textValue(
                                                (variant.product as Row)?.name,
                                            ),
                                        )
                                    }
                                >
                                    {w('Qo‘shish', 'Добавить', 'Add')}
                                </button>
                            </div>
                        ))}
                        {!products.isPending &&
                            rows(products.data).length === 0 && (
                                <p className="p-6 text-sm text-neutral-500">
                                    {w(
                                        'Tovar topilmadi. Yo‘qotilgan savdoni qayd eting.',
                                        'Товар не найден. Зафиксируйте упущенную продажу.',
                                        'No products found. Record a lost sale.',
                                    )}
                                </p>
                            )}
                    </div>
                    {permissions.includes('catalog.product.quick_create') && (
                        <QuickProduct
                            onCreated={(product) => {
                                const variant = rows(product.variants)[0]
                                if (variant)
                                    void addVariant(
                                        variant,
                                        String(product.name),
                                    )
                                setSearch('')
                            }}
                        />
                    )}
                    <details>
                        <summary className="cursor-pointer py-2">
                            {w(
                                'Xizmat qo‘shish',
                                'Добавить услугу',
                                'Add service',
                            )}
                        </summary>
                        <Services onAdd={add} />
                    </details>
                    <CustomLens onAdd={add} />
                    {permissions.includes('warehouse.lost_sale.create') && (
                        <details>
                            <summary className="cursor-pointer py-2">
                                {w(
                                    'Yo‘qotilgan savdo',
                                    'Упущенная продажа',
                                    'Record lost sale',
                                )}
                            </summary>
                            <DomainForm
                                fields={[
                                    f('search_term'),
                                    select('reason', [
                                        'out_of_stock',
                                        'not_in_catalog',
                                        'price',
                                    ]),
                                ]}
                                initial={{ search_term: search }}
                                onSave={(body) =>
                                    api.post('/lost-sales', {
                                        body: {
                                            ...body,
                                            branch_id: user?.branch_id,
                                            customer_id: customerId,
                                        },
                                        idempotencyKey: idempotencyKey(),
                                    })
                                }
                            />
                        </details>
                    )}
                </section>
                <aside className="space-y-4 rounded-xl border border-neutral-200 bg-white p-5">
                    <h2 className="text-lg font-semibold">
                        {w('Savat', 'Корзина', 'Cart')}
                    </h2>
                    <label className="grid gap-2 text-sm">
                        {w(
                            'Mijoz · ism yoki telefon',
                            'Клиент · имя или телефон',
                            'Customer · name or phone',
                        )}
                        <input
                            className="rounded-lg border border-neutral-300 p-2"
                            value={customer}
                            onChange={(e) => {
                                setCustomer(e.target.value)
                                setCustomerId(null)
                                setPrescriptionId('')
                            }}
                        />
                    </label>
                    {customerId === null && customer.length >= 2 && (
                        <div className="max-h-40 overflow-y-auto">
                            {rows(customers.data).map((c) => (
                                <button
                                    key={c.id}
                                    className="block w-full p-2 text-left text-sm hover:bg-neutral-50"
                                    onClick={() => {
                                        setCustomerId(c.id)
                                        setCustomer(textValue(c.name))
                                    }}
                                >
                                    {textValue(c.name)} · {textValue(c.phone)}
                                </button>
                            ))}
                        </div>
                    )}
                    {permissions.includes('sales.customer.create') && (
                        <details>
                            <summary className="cursor-pointer text-sm">
                                {w(
                                    'Yangi mijoz',
                                    'Новый клиент',
                                    'New customer',
                                )}
                            </summary>
                            <ResourceCreate
                                resource={resources.find(
                                    (r) => r.path === 'customers',
                                )!}
                                onDone={() => setCustomer('')}
                            />
                        </details>
                    )}
                    {customerId && (
                        <p className="text-sm font-semibold text-red-700">
                            {w('Mijoz qarzi', 'Долг клиента', 'Customer debt')}:{' '}
                            {textValue(
                                (selectedCustomer.data as { data?: Row })?.data
                                    ?.debt ??
                                    (selectedCustomer.data as { data?: Row })
                                        ?.data?.debt_balance,
                            )}
                        </p>
                    )}
                    <label className="grid gap-2 text-sm">
                        {w('Savdo turi', 'Тип продажи', 'Sale type')}
                        <select
                            className="rounded-lg border p-2"
                            value={type}
                            onChange={(e) => setType(e.target.value)}
                        >
                            <option value="quick">
                                {w(
                                    'Tez savdo',
                                    'Быстрая продажа',
                                    'Quick sale',
                                )}
                            </option>
                            <option value="order">
                                {w('Buyurtma', 'Заказ', 'Made-to-order')}
                            </option>
                        </select>
                    </label>
                    {type === 'order' && (
                        <div className="space-y-3">
                            <label className="grid gap-2 text-sm">
                                {w('Muddat', 'Срок', 'Due date')}
                                <input
                                    className="rounded-lg border p-2"
                                    type="date"
                                    value={dueDate}
                                    onChange={(e) => setDueDate(e.target.value)}
                                />
                            </label>
                            <label className="grid gap-2 text-sm">
                                {w('Topshirish', 'Выдача', 'Delivery')}
                                <select
                                    className="rounded-lg border p-2"
                                    value={deliveryType}
                                    onChange={(e) =>
                                        setDeliveryType(e.target.value)
                                    }
                                >
                                    <option value="pickup">
                                        {w(
                                            'Filialdan olish',
                                            'Самовывоз',
                                            'Pickup',
                                        )}
                                    </option>
                                    <option value="courier">
                                        {w('Yetkazish', 'Курьер', 'Courier')}
                                    </option>
                                </select>
                            </label>
                            <label className="grid gap-2 text-sm">
                                {w(
                                    'Faol retsept',
                                    'Активный рецепт',
                                    'Active prescription',
                                )}
                                <select
                                    className="rounded-lg border p-2"
                                    value={prescriptionId}
                                    onChange={(e) =>
                                        setPrescriptionId(e.target.value)
                                    }
                                >
                                    <option value="">—</option>
                                    {rows(prescriptions.data).map((rx) => (
                                        <option key={rx.id} value={rx.id}>
                                            #{rx.id} ·{' '}
                                            {textValue(rx.created_at)}
                                            {rx.is_expired
                                                ? ' · ' +
                                                  w(
                                                      'Muddati o‘tgan — qayta ko‘rik tavsiya etiladi',
                                                      'Просрочен — рекомендован осмотр',
                                                      'Expired — checkup recommended',
                                                  )
                                                : ''}
                                        </option>
                                    ))}
                                </select>
                            </label>
                        </div>
                    )}
                    <div className="divide-y divide-neutral-100">
                        {cart.map((item, index) => (
                            <div
                                key={item.kind + item.id + index}
                                className="space-y-2 py-3"
                            >
                                <div className="text-sm font-medium">
                                    {item.name}
                                </div>
                                <div className="flex items-center justify-between gap-3">
                                    <input
                                        aria-label={w(
                                            'Miqdor',
                                            'Количество',
                                            'Quantity',
                                        )}
                                        type="number"
                                        min="1"
                                        step="1"
                                        className="w-20 rounded border p-2"
                                        value={item.quantity}
                                        onChange={(e) =>
                                            setCart((old) =>
                                                old.map((v, i) =>
                                                    i === index
                                                        ? {
                                                              ...v,
                                                              quantity:
                                                                  Math.max(
                                                                      1,
                                                                      Number(
                                                                          e
                                                                              .target
                                                                              .value,
                                                                      ),
                                                                  ),
                                                          }
                                                        : v,
                                                ),
                                            )
                                        }
                                    />
                                    <span className="text-sm tabular-nums">
                                        {item.price} × {item.quantity}
                                    </span>
                                    <button
                                        className="text-sm text-red-700"
                                        onClick={() =>
                                            setCart((old) =>
                                                old.filter(
                                                    (_, i) => i !== index,
                                                ),
                                            )
                                        }
                                    >
                                        {w('O‘chirish', 'Удалить', 'Remove')}
                                    </button>
                                </div>
                            </div>
                        ))}
                    </div>
                    <label className="grid gap-2 text-sm">
                        {w('Chegirma', 'Скидка', 'Discount')}
                        <input
                            className="rounded-lg border p-2"
                            type="number"
                            min="0"
                            step="0.01"
                            value={discount}
                            onChange={(e) => setDiscount(e.target.value)}
                        />
                    </label>
                    <p className="flex justify-between border-t pt-4 text-lg font-semibold">
                        <span>
                            {w(
                                'Taxminiy jami',
                                'Предварительный итог',
                                'Estimated total',
                            )}
                        </span>
                        <span className="tabular-nums">{money(estimate)}</span>
                    </p>
                    <p className="text-xs text-neutral-500">
                        {w(
                            'Yakuniy narx va yaxlitlashni server hisoblaydi.',
                            'Окончательную цену и округление рассчитывает сервер.',
                            'The server calculates final prices and rounding.',
                        )}
                    </p>
                    {type === 'quick' && (
                        <div className="space-y-2 border-t pt-3">
                            <h3 className="text-sm font-medium">
                                {w('To‘lovlar', 'Оплата', 'Payments')}
                            </h3>
                            {checkoutPayments.map((p, i) => (
                                <div
                                    className="flex justify-between text-sm"
                                    key={i}
                                >
                                    <span>
                                        {p.method} · {p.amount}
                                    </span>
                                    <button
                                        onClick={() =>
                                            setCheckoutPayments((v) =>
                                                v.filter((_, j) => j !== i),
                                            )
                                        }
                                    >
                                        {w('O‘chirish', 'Удалить', 'Remove')}
                                    </button>
                                </div>
                            ))}
                            <div className="flex flex-wrap gap-2">
                                <input
                                    id="checkout-amount"
                                    aria-label={w('Summa', 'Сумма', 'Amount')}
                                    className="min-w-0 w-28 rounded border p-2 text-sm"
                                    type="number"
                                    min="0.01"
                                    step="0.01"
                                    value={checkoutAmount}
                                    onChange={(e) =>
                                        setCheckoutAmount(e.target.value)
                                    }
                                />
                                <select
                                    aria-label={w(
                                        'To‘lov usuli',
                                        'Способ оплаты',
                                        'Payment method',
                                    )}
                                    className="rounded border p-2 text-sm"
                                    value={checkoutMethod}
                                    onChange={(e) =>
                                        setCheckoutMethod(e.target.value)
                                    }
                                >
                                    {['cash', 'card', 'transfer'].map((m) => (
                                        <option key={m} value={m}>
                                            {m}
                                        </option>
                                    ))}
                                </select>
                                <button
                                    className="action-secondary"
                                    disabled={Number(checkoutAmount) <= 0}
                                    onClick={() => {
                                        setCheckoutPayments((v) => [
                                            ...v,
                                            {
                                                amount: checkoutAmount,
                                                method: checkoutMethod,
                                            },
                                        ])
                                        setCheckoutAmount('')
                                    }}
                                >
                                    {w('Qo‘shish', 'Добавить', 'Add')}
                                </button>
                            </div>
                        </div>
                    )}
                    <ErrorNotice error={create.error} />
                    <button
                        id="pos-checkout"
                        className="action-primary w-full"
                        disabled={
                            !cart.length || !user?.branch_id || create.isPending
                        }
                        onClick={() => create.mutate()}
                    >
                        {w(
                            'Rasmiylashtirish',
                            'Оформить продажу',
                            'Create sale',
                        )}
                    </button>
                </aside>
            </div>
            {order && (
                <section className="space-y-4 rounded-xl border border-neutral-200 bg-white p-5">
                    <h2 className="text-lg font-semibold">
                        {w(
                            'Buyurtma yaratildi',
                            'Заказ создан',
                            'Order created',
                        )}{' '}
                        · {textValue(order.number)} · {textValue(order.total)}{' '}
                        UZS
                    </h2>
                    <form
                        className="flex flex-wrap gap-3"
                        onSubmit={(e) => {
                            e.preventDefault()
                            pay.mutate()
                        }}
                    >
                        <input
                            aria-label={w(
                                'To‘lov summasi',
                                'Сумма оплаты',
                                'Payment amount',
                            )}
                            className="rounded-lg border p-2"
                            type="number"
                            min="0.01"
                            step="0.01"
                            value={payment}
                            onChange={(e) => setPayment(e.target.value)}
                            required
                        />
                        <select
                            aria-label={w(
                                'To‘lov usuli',
                                'Способ оплаты',
                                'Payment method',
                            )}
                            className="rounded-lg border p-2"
                            value={method}
                            onChange={(e) => setMethod(e.target.value)}
                        >
                            {['cash', 'card', 'transfer'].map((m) => (
                                <option key={m} value={m}>
                                    {m}
                                </option>
                            ))}
                        </select>
                        <button
                            className="action-primary"
                            disabled={pay.isPending}
                        >
                            {w(
                                'To‘lovni qabul qilish',
                                'Принять оплату',
                                'Accept payment',
                            )}
                        </button>
                    </form>
                    <ErrorNotice error={pay.error} />
                    {pay.isSuccess && (
                        <p role="status">
                            {w(
                                'To‘lov qabul qilindi',
                                'Оплата принята',
                                'Payment accepted',
                            )}
                        </p>
                    )}
                    <ResourceDetails
                        resource={resources.find((r) => r.path === 'orders')!}
                        row={order}
                        onClose={() => setOrder(null)}
                    />
                </section>
            )}
        </div>
    )
}
function Services({ onAdd }: { onAdd: (v: CartItem) => void }) {
    const q = useList('/services')
    return (
        <div className="flex flex-wrap gap-2 py-3">
            {rows(q.data).map((s) => (
                <button
                    key={s.id}
                    className="action-secondary"
                    onClick={() =>
                        onAdd({
                            id: s.id,
                            kind: 'service',
                            name: textValue(s.name),
                            quantity: 1,
                            price: String(s.price),
                        })
                    }
                >
                    {textValue(s.name)} · {textValue(s.price)}
                </button>
            ))}
        </div>
    )
}
