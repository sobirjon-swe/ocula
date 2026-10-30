import {
    DomainForm,
    f,
    lookup,
    select,
    rows,
    textValue,
    useList,
} from './shared'
import { useWords } from './copy'
export type CustomLensItem = {
    id: number
    kind: 'service'
    name: string
    quantity: number
    price: string
    cost_total: string
    custom_lens_params: Record<string, unknown>
}
export function CustomLens({
    onAdd,
}: {
    onAdd: (item: CustomLensItem) => void
}) {
    const w = useWords(),
        services = useList('/services')
    const diopters = Array.from({ length: 241 }, (_, i) =>
        ((i - 120) / 4).toFixed(2),
    )
    return (
        <details>
            <summary className="cursor-pointer py-2">
                {w(
                    'Individual linza buyurtmasi',
                    'Заказ индивидуальных линз',
                    'Custom lens order',
                )}
            </summary>
            <div className="rounded-lg border border-neutral-200 p-4">
                <DomainForm
                    fields={[
                        lookup('service_id', '/services'),
                        f('cost_total', 'decimal'),
                        select('od_sph', diopters, true),
                        select('os_sph', diopters, true),
                        f('index', 'decimal', true),
                        f('coating', 'text', true),
                        f('notes', 'textarea', true),
                    ]}
                    submitLabel={w(
                        'Savatga qo‘shish',
                        'Добавить в корзину',
                        'Add to cart',
                    )}
                    onSave={async (body) => {
                        const service = rows(services.data).find(
                            (s) => s.id === Number(body.service_id),
                        )
                        if (!service)
                            throw new Error(
                                w(
                                    'Xizmatni tanlang',
                                    'Выберите услугу',
                                    'Choose a service',
                                ),
                            )
                        onAdd({
                            id: service.id,
                            kind: 'service',
                            name:
                                textValue(service.name) +
                                ' · ' +
                                w(
                                    'Individual linza',
                                    'Индивидуальная линза',
                                    'Custom lens',
                                ),
                            quantity: 1,
                            price: String(service.price),
                            cost_total: String(body.cost_total),
                            custom_lens_params: Object.fromEntries(
                                Object.entries(body).filter(
                                    ([k]) =>
                                        !['service_id', 'cost_total'].includes(
                                            k,
                                        ),
                                ),
                            ),
                        })
                    }}
                />
            </div>
        </details>
    )
}
