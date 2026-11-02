import { QuickDefect } from './QuickDefect'
import { useState } from 'react'
import { useMutation } from '@tanstack/react-query'
import { useSession } from '@/lib/session'
import { useWords, useLabel } from './copy'
import {
    ErrorNotice,
    rows,
    textValue,
    useList,
    useWrite,
    type Row,
} from './shared'
import { resources } from './resources'
import { ResourceDetails } from './ResourcePage'
export function WorkshopPage() {
    const w = useWords(),
        label = useLabel(),
        permissions = useSession((s) => s.permissions),
        [filter, setFilter] = useState('queued'),
        [selected, setSelected] = useState<Row | null>(null),
        q = useList('/work-orders', { per_page: 100, include: 'items' }),
        write = useWrite(),
        [dragged, setDragged] = useState<Row | null>(null)
    const mutation = useMutation({
        mutationFn: ({ row, target }: { row: Row; target: string }) =>
            write('/work-orders/' + row.id + '/' + target),
    })
    function move(row: Row, status: string) {
        if (
            status === 'in_progress' &&
            row.status === 'queued' &&
            permissions.includes('workshop.work_order.start')
        )
            mutation.mutate({ row, target: 'start' })
        if (
            status === 'done' &&
            row.status === 'in_progress' &&
            permissions.includes('workshop.work_order.finish')
        )
            mutation.mutate({ row, target: 'finish' })
    }
    function card(row: Row) {
        const due = row.due_at ? new Date(String(row.due_at)) : null,
            today =
                due &&
                due.toLocaleDateString('en-CA', {
                    timeZone: 'Asia/Tashkent',
                }) ===
                    new Date().toLocaleDateString('en-CA', {
                        timeZone: 'Asia/Tashkent',
                    }),
            rx = row.prescription as
                | { od?: Record<string, unknown>; os?: Record<string, unknown> }
                | undefined
        return (
            <article
                draggable
                onDragStart={() => setDragged(row)}
                key={row.id}
                className={
                    'space-y-3 rounded-xl border p-4 ' +
                    (row.is_overdue
                        ? 'border-red-300 bg-red-50'
                        : today
                          ? 'border-amber-300 bg-amber-50'
                          : 'border-neutral-200 bg-white')
                }
            >
                <div className="flex items-start justify-between gap-2">
                    <h3 className="font-semibold">
                        {textValue(row.order_number ?? row.order_id)} ·{' '}
                        {textValue(row.customer_name)}
                    </h3>
                    <span className="text-xs">
                        {label(textValue(row.priority))}
                    </span>
                </div>
                {rx && (
                    <div className="grid gap-2 text-xl font-semibold tabular-nums">
                        {['od', 'os'].map((eye) => (
                            <p key={eye}>
                                {eye.toUpperCase()}:{' '}
                                {textValue(rx[eye as 'od' | 'os']?.sph)} /{' '}
                                {textValue(rx[eye as 'od' | 'os']?.cyl)} ×{' '}
                                {textValue(rx[eye as 'od' | 'os']?.axis)}
                            </p>
                        ))}
                    </div>
                )}
                <p className="text-sm">
                    {textValue(row.frame_label ?? row.note)}
                </p>
                <p
                    className={
                        'text-sm ' +
                        (row.is_overdue
                            ? 'font-semibold text-red-800'
                            : 'text-neutral-600')
                    }
                >
                    {due
                        ? due.toLocaleString('ru-RU', {
                              timeZone: 'Asia/Tashkent',
                              dateStyle: 'short',
                              timeStyle: 'short',
                          })
                        : '—'}
                </p>
                <div className="flex flex-wrap gap-2">
                    <button
                        className="action-secondary min-h-[60px] min-w-[60px]"
                        onClick={() => setSelected(row)}
                    >
                        {w('Ochish', 'Открыть', 'Open')}
                    </button>
                    {row.status === 'queued' &&
                        permissions.includes('workshop.work_order.start') && (
                            <button
                                className="action-primary min-h-[60px]"
                                disabled={mutation.isPending}
                                onClick={() => move(row, 'in_progress')}
                            >
                                {w('Boshlash', 'Начать', 'Start')}
                            </button>
                        )}
                    {row.status === 'in_progress' &&
                        permissions.includes('workshop.work_order.finish') && (
                            <button
                                className="action-primary min-h-[60px]"
                                disabled={mutation.isPending}
                                onClick={() => move(row, 'done')}
                            >
                                {w('Tayyor', 'Готово', 'Done')}
                            </button>
                        )}
                </div>
                {permissions.includes('workshop.work_order.defect') &&
                    ['queued', 'in_progress'].includes(String(row.status)) && (
                        <QuickDefect row={row} />
                    )}
            </article>
        )
    }
    return (
        <div className="space-y-5">
            <h1 className="text-2xl font-semibold">
                {w('Ustaxona', 'Мастерская', 'Workshop')}
            </h1>
            <ErrorNotice error={q.error ?? mutation.error} />
            {selected && (
                <ResourceDetails
                    key={selected.id}
                    resource={resources.find((r) => r.path === 'work-orders')!}
                    row={selected}
                    onClose={() => setSelected(null)}
                />
            )}
            <div className="lg:hidden">
                <label className="grid gap-2 text-sm">
                    {w('Holat', 'Статус', 'Status')}
                    <select
                        className="rounded-lg border p-3"
                        value={filter}
                        onChange={(e) => setFilter(e.target.value)}
                    >
                        {['queued', 'in_progress', 'done', 'defect'].map(
                            (status) => (
                                <option key={status} value={status}>
                                    {label(status)}
                                </option>
                            ),
                        )}
                    </select>
                </label>
                <div className="mt-4 space-y-3">
                    {rows(q.data)
                        .filter((row) => row.status === filter)
                        .map(card)}
                </div>
            </div>
            <div className="hidden grid-cols-3 items-start gap-4 lg:grid">
                {['queued', 'in_progress', 'done'].map((status) => (
                    <section
                        key={status}
                        className="min-h-48 space-y-3 rounded-xl bg-neutral-100 p-3"
                        onDragOver={(e) => e.preventDefault()}
                        onDrop={(e) => {
                            e.preventDefault()
                            if (dragged) move(dragged, status)
                            setDragged(null)
                        }}
                    >
                        <h2 className="p-1 font-semibold">
                            {label(status)} ·{' '}
                            {
                                rows(q.data).filter((r) => r.status === status)
                                    .length
                            }
                        </h2>
                        {rows(q.data)
                            .filter((row) => row.status === status)
                            .map(card)}
                    </section>
                ))}
            </div>
        </div>
    )
}
