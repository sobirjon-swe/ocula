import { useState } from 'react'
import { DomainForm, f, lookup, select, useWrite, type Row } from './shared'
import { useWords } from './copy'
export function QuickProduct({
    onCreated,
}: {
    onCreated: (product: Row) => void
}) {
    const w = useWords(),
        write = useWrite()
    return (
        <details>
            <summary className="cursor-pointer py-2">
                {w(
                    'Tovarni tez qo‘shish',
                    'Быстро добавить товар',
                    'Quick-add product',
                )}
            </summary>
            <div className="space-y-3 rounded-lg border border-neutral-200 p-4">
                <p className="text-sm text-neutral-600">
                    {w(
                        'Haqiqiy sanalgan qoldiq va tannarxni kiriting. Kirim yozilib, tovar savatga qo‘shiladi.',
                        'Введите фактически подсчитанный остаток и себестоимость. Приход будет записан, товар добавится в корзину.',
                        'Enter the physically counted stock and unit cost. The opening receipt is recorded and the item is added to the cart.',
                    )}
                </p>
                <DomainForm
                    initial={{ opening_quantity: 1 }}
                    fields={[
                        f('name'),
                        select('type', ['frame', 'lens', 'ready', 'accessory']),
                        f('barcode', 'text', true),
                        f('price', 'decimal'),
                        lookup('branch_id', '/branches'),
                        f('opening_quantity', 'number'),
                        f('cost_price', 'decimal'),
                    ]}
                    onSave={async (body) => {
                        const result = (await write(
                            '/products/quick',
                            body,
                        )) as { data: Row }
                        onCreated(result.data)
                    }}
                />
            </div>
        </details>
    )
}
export function DeviceReceipt({
    device,
    onDone,
}: {
    device: Row
    onDone: () => void
}) {
    const w = useWords(),
        [linked, setLinked] = useState(false)
    return (
        <section className="space-y-3 rounded-xl bg-amber-50 p-5 text-amber-950">
            <h3 className="font-semibold">
                {w(
                    'Qurilma yaratildi',
                    'Устройство зарегистрировано',
                    'Device registered',
                )}{' '}
                · #{device.id}
            </h3>
            <p className="text-sm">
                {w(
                    'Token faqat hozir ko‘rinadi. Uni planshetga kiriting yoki shu qurilmani ulang.',
                    'Токен показывается только сейчас. Введите его на планшете или подключите это устройство.',
                    'The token is shown once. Enter it on the tablet or connect this device.',
                )}
            </p>
            <code className="block break-all rounded bg-white p-3 text-sm">
                {String(device.token)}
            </code>
            <button
                className="action-primary"
                onClick={() => {
                    localStorage.setItem('optika.device.id', String(device.id))
                    localStorage.setItem(
                        'optika.device.token',
                        String(device.token),
                    )
                    setLinked(true)
                }}
            >
                {w(
                    'Shu qurilmani ulash',
                    'Подключить это устройство',
                    'Connect this device',
                )}
            </button>
            {linked && (
                <p role="status">
                    {w(
                        'Ulandi — chiqishdan keyin PIN bilan kiring.',
                        'Подключено — после выхода войдите по PIN.',
                        'Connected — sign in with PIN after signing out.',
                    )}
                </p>
            )}
            <button className="action-secondary" onClick={onDone}>
                {w('Yopish', 'Закрыть', 'Close')}
            </button>
        </section>
    )
}
