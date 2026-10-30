import { downloadFile } from '@/lib/api/client'
import { useMutation } from '@tanstack/react-query'
import { Dashboard } from './Dashboard'
import { money } from './money'
import { useState } from 'react'
import { useSession } from '@/lib/session'
import { useWords, useLabel } from './copy'
import {
    Skeleton,
    DataTable,
    DomainForm,
    ErrorNotice,
    f,
    rows,
    textValue,
    useList,
    useWrite,
} from './shared'
const reports = [
    [
        'debt-aging',
        'finance.debt.view_any',
        'Qarz yoshi',
        'Возраст долгов',
        'Debt aging',
    ],
    [
        'turnover',
        'analytics.stock_report.view',
        'Tovar aylanishi',
        'Оборачиваемость',
        'Stock turnover',
    ],
    [
        'abandoned-orders',
        'analytics.profit_report.view',
        'Olib ketilmagan buyurtmalar',
        'Невостребованные заказы',
        'Abandoned orders',
    ],
    [
        'transport-costs',
        'analytics.cash_report.view',
        'Tashish xarajatlari',
        'Расходы на перевозку',
        'Transport costs',
    ],
    [
        'supplier-balances',
        'finance.supplier.balance.view',
        'Ta’minotchilar balansi',
        'Балансы поставщиков',
        'Supplier balances',
    ],
    [
        'dashboard',
        'analytics.dashboard.view',
        'Bosh ekran',
        'Обзор',
        'Overview',
    ],
    [
        'cash-report',
        'analytics.cash_report.view',
        'Kassa',
        'Касса',
        'Cash report',
    ],
    [
        'profit-report',
        'analytics.profit_report.view',
        'Foyda',
        'Прибыль',
        'Profit report',
    ],
    [
        'abc-analysis',
        'analytics.stock_report.view',
        'ABC tahlil',
        'ABC-анализ',
        'ABC analysis',
    ],
    [
        'dead-stock',
        'analytics.stock_report.view',
        'O‘lik zaxira',
        'Неликвиды',
        'Dead stock',
    ],
    [
        'lost-sale-report',
        'warehouse.lost_sale.view_any',
        'Yo‘qotilgan savdo',
        'Упущенные продажи',
        'Lost sales',
    ],
    [
        'staff-report',
        'analytics.staff_report.view',
        'Xodimlar',
        'Сотрудники',
        'Staff',
    ],
    [
        'branch-rating',
        'analytics.branch_rating.view',
        'Filiallar reytingi',
        'Рейтинг филиалов',
        'Branch rating',
    ],
]
export function ReportsPage() {
    const w = useWords(),
        p = useSession((s) => s.permissions),
        visible = reports.filter((r) => p.includes(r[1])),
        [report, setReport] = useState(
            visible.find((r) => r[0] === 'dashboard')?.[0] ??
                visible[0]?.[0] ??
                'dashboard',
        ),
        [from, setFrom] = useState(new Date().toISOString().slice(0, 8) + '01'),
        [to, setTo] = useState(new Date().toISOString().slice(0, 10)),
        [branch, setBranch] = useState(''),
        branches = useList('/branches', {}, p.includes('core.branch.view_any')),
        q = useList(
            '/analytics/' + report,
            { from, to, period: from.slice(0, 7), branch_id: branch },
            visible.length > 0,
        ),
        label = useLabel()
    const download = useMutation({
        mutationFn: (format: string) => {
            const operational = [
                'debt-aging',
                'turnover',
                'abandoned-orders',
                'transport-costs',
                'supplier-balances',
            ].includes(report)
            return downloadFile(
                '/analytics/' + (operational ? report : 'exports/' + report),
                report + '-' + to + '.' + format,
                {
                    from,
                    to,
                    branch_id: branch,
                    period: from.slice(0, 7),
                    format,
                },
            )
        },
    })
    const body = (q.data as { data?: unknown })?.data ?? q.data
    function render(value: unknown, key = ''): React.ReactNode {
        if (Array.isArray(value)) {
            const list = rows(value),
                columns = Object.keys(list[0] ?? {}).filter(
                    (k) => typeof list[0][k] !== 'object',
                )
            return (
                <div key={key} className="space-y-3">
                    <h2 className="text-lg font-semibold">{label(key)}</h2>
                    <DataTable data={list} columns={columns} />
                </div>
            )
        }
        if (value && typeof value === 'object')
            return (
                <div key={key} className="space-y-5">
                    {key && (
                        <h2 className="text-lg font-semibold">{label(key)}</h2>
                    )}
                    <dl className="divide-y divide-neutral-200">
                        {Object.entries(value)
                            .filter(([, v]) => typeof v !== 'object')
                            .map(([k, v]) => (
                                <div
                                    key={k}
                                    className="flex justify-between gap-6 py-3"
                                >
                                    <dt className="text-sm text-neutral-600">
                                        {label(k)}
                                    </dt>
                                    <dd className="text-right font-medium tabular-nums">
                                        {/(amount|total|net|revenue|profit|cost|expense|liability|cash|debt)/.test(
                                            k,
                                        )
                                            ? money(v)
                                            : textValue(v)}
                                    </dd>
                                </div>
                            ))}
                    </dl>
                    {Object.entries(value)
                        .filter(([, v]) => v !== null && typeof v === 'object')
                        .map(([k, v]) => render(v, k))}
                </div>
            )
        return null
    }
    return (
        <div className="space-y-6">
            <h1 className="text-2xl font-semibold">
                {w('Hisobotlar', 'Отчёты', 'Reports')}
            </h1>
            <div className="flex flex-wrap gap-2 border-b border-neutral-200 pb-3">
                {visible.map((r) => (
                    <button
                        key={r[0]}
                        className={
                            report === r[0]
                                ? 'action-primary'
                                : 'action-secondary'
                        }
                        onClick={() => setReport(r[0])}
                    >
                        {w(r[2], r[3], r[4])}
                    </button>
                ))}
            </div>
            <div className="flex flex-wrap items-end gap-3">
                <label className="grid gap-1 text-sm">
                    {w('Boshlanish', 'С', 'From')}
                    <input
                        type="date"
                        className="rounded border p-2"
                        value={from}
                        onChange={(e) => setFrom(e.target.value)}
                    />
                </label>
                <label className="grid gap-1 text-sm">
                    {w('Tugash', 'По', 'To')}
                    <input
                        type="date"
                        className="rounded border p-2"
                        value={to}
                        onChange={(e) => setTo(e.target.value)}
                    />
                </label>
                <button
                    className="action-secondary"
                    disabled={
                        report === 'dashboard' ||
                        !p.includes('analytics.export') ||
                        download.isPending
                    }
                    onClick={() => download.mutate('csv')}
                >
                    CSV
                </button>
                <button
                    className="action-secondary"
                    disabled={
                        report === 'dashboard' ||
                        !p.includes('analytics.export') ||
                        download.isPending
                    }
                    onClick={() => download.mutate('xlsx')}
                >
                    XLSX
                </button>
                <button
                    className="action-secondary"
                    onClick={() => window.print()}
                >
                    {w('Chop etish', 'Печать', 'Print')}
                </button>
            </div>
            <label className="grid max-w-xs gap-1 text-sm">
                {label('branch_id')}
                <select
                    className="rounded-lg border border-neutral-300 bg-white p-2"
                    value={branch}
                    onChange={(e) => setBranch(e.target.value)}
                >
                    <option value="">
                        {w('Barcha filiallar', 'Все филиалы', 'All branches')}
                    </option>
                    {rows(branches.data).map((b) => (
                        <option key={b.id} value={b.id}>
                            {textValue(b.name)}
                        </option>
                    ))}
                </select>
            </label>
            <ErrorNotice error={q.error ?? download.error} />
            {q.isPending ? (
                <Skeleton />
            ) : (
                <section className="max-w-5xl rounded-xl border border-neutral-200 bg-white p-5">
                    {report === 'dashboard' &&
                    body &&
                    typeof body === 'object' ? (
                        <Dashboard data={body as Record<string, unknown>} />
                    ) : (
                        render(body)
                    )}
                </section>
            )}
        </div>
    )
}
export function SettingsPage() {
    const w = useWords(),
        q = useList('/settings'),
        write = useWrite()
    const body = (q.data as { data?: Record<string, unknown> })?.data
    const entries = body ? Object.entries(body) : []
    return (
        <div className="space-y-5">
            <h1 className="text-2xl font-semibold">
                {w('Sozlamalar', 'Настройки', 'Settings')}
            </h1>
            <ErrorNotice error={q.error} />
            {body && (
                <DomainForm
                    key={JSON.stringify(body)}
                    fields={entries.map(([k, v]) =>
                        f(k, typeof v === 'number' ? 'number' : 'text'),
                    )}
                    initial={Object.fromEntries(
                        entries.map(([k, v]) => [
                            k,
                            Array.isArray(v) ? v.join(', ') : v,
                        ]),
                    )}
                    onSave={(body) =>
                        write(
                            '/settings',
                            {
                                ...body,
                                ...(body.debt_reminder_days
                                    ? {
                                          debt_reminder_days: String(
                                              body.debt_reminder_days,
                                          )
                                              .split(',')
                                              .map((v) => Number(v.trim())),
                                      }
                                    : {}),
                            },
                            'put',
                        )
                    }
                />
            )}
        </div>
    )
}
