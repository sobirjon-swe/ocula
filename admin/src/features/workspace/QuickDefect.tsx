import { useState } from 'react'
import { useMutation } from '@tanstack/react-query'
import { useLabel, useWords } from './copy'
import { ErrorNotice, rows, textValue, useWrite, type Row } from './shared'
export function QuickDefect({ row }: { row: Row }) {
    const w = useWords(),
        label = useLabel(),
        write = useWrite(),
        [open, setOpen] = useState(false),
        items = rows(row.items),
        [item, setItem] = useState(
            items.length === 1 ? String(items[0].id) : '',
        ),
        [quantity, setQuantity] = useState('1'),
        mutation = useMutation({
            mutationFn: (reason: string) =>
                write('/work-orders/' + row.id + '/defect', {
                    item_id: Number(item),
                    quantity: Number(quantity),
                    reason,
                    note: label(reason) + ' · #' + row.id,
                }),
        })
    return (
        <div className="w-full">
            <button
                className="min-h-[60px] min-w-[60px] rounded-lg border border-red-300 px-4 text-sm font-semibold text-red-800 hover:bg-red-50"
                aria-expanded={open}
                onClick={() => setOpen(!open)}
            >
                {w('Brak', 'Дефект', 'Defect')}
            </button>
            {open && (
                <div className="mt-3 space-y-3 rounded-lg bg-white p-3">
                    <label className="grid gap-2 text-sm">
                        {w(
                            'Brak material',
                            'Дефектный материал',
                            'Defective material',
                        )}
                        <select
                            className="min-h-[60px] rounded-lg border border-neutral-300 bg-white p-2"
                            value={item}
                            onChange={(e) => setItem(e.target.value)}
                        >
                            <option value="">
                                {w('Tanlang', 'Выберите', 'Choose')}
                            </option>
                            {items.map((i) => (
                                <option key={i.id} value={i.id}>
                                    {textValue((i.variant as Row)?.product)} ·{' '}
                                    {textValue(i.variant ?? i.variant_id)}
                                </option>
                            ))}
                        </select>
                    </label>
                    <label className="grid gap-2 text-sm">
                        {label('quantity')}
                        <input
                            className="min-h-[60px] rounded-lg border border-neutral-300 p-2"
                            type="number"
                            min="1"
                            max={Number(
                                items.find((i) => String(i.id) === item)
                                    ?.quantity ?? 1,
                            )}
                            value={quantity}
                            onChange={(e) => setQuantity(e.target.value)}
                        />
                    </label>
                    <div className="grid gap-2">
                        {[
                            'supplier_defect',
                            'transport_damage',
                            'master_error',
                            'customer_request',
                        ].map((reason) => (
                            <button
                                key={reason}
                                className="min-h-[60px] rounded-lg border border-neutral-300 p-3 text-left text-sm font-medium hover:bg-neutral-50 disabled:opacity-50"
                                disabled={
                                    !item ||
                                    Number(quantity) < 1 ||
                                    mutation.isPending ||
                                    mutation.isSuccess
                                }
                                onClick={() => mutation.mutate(reason)}
                            >
                                {label(reason)}
                            </button>
                        ))}
                    </div>
                    <ErrorNotice error={mutation.error} />
                    {mutation.isSuccess && (
                        <p role="status" className="text-sm text-green-800">
                            {w(
                                'Brak qayd etildi',
                                'Дефект зарегистрирован',
                                'Defect recorded',
                            )}
                        </p>
                    )}
                </div>
            )}
        </div>
    )
}
