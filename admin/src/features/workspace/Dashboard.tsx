import { Link } from 'react-router-dom'
import { useLabel, useWords } from './copy'
import { money } from './money'
import { DataTable, rows, textValue, type Row } from './shared'
export function Dashboard({ data }: { data: Record<string, unknown> }) {
    const label = useLabel(),
        w = useWords()
    const metrics = [
        'cash_net_today',
        'net_profit_today',
        'total_debt',
        'overdue_orders',
    ]
    function target(href: unknown) {
        const path = String(href ?? '/reports')
        if (
            path === '/orders' ||
            path === '/debts' ||
            path === '/transfers' ||
            path === '/shifts' ||
            path === '/lost-sales' ||
            path === '/products'
        )
            return '/work' + path
        return path.startsWith('/api/') ? '/reports' : path
    }
    return (
        <div className="space-y-8">
            <dl className="grid grid-cols-2 gap-x-5 gap-y-6 lg:grid-cols-4">
                {metrics.map((key) => (
                    <div
                        key={key}
                        className="space-y-2 border-b border-neutral-200 pb-4"
                    >
                        <dt className="text-sm text-neutral-600">
                            {label(key)}
                        </dt>
                        <dd className="text-2xl font-semibold tabular-nums sm:text-3xl">
                            {key === 'overdue_orders'
                                ? textValue(data[key])
                                : money(data[key])}
                        </dd>
                    </div>
                ))}
            </dl>
            <div className="rounded-lg bg-brand-50 p-4 text-sm text-brand-900">
                {label('outstanding_liability')}:{' '}
                <strong>{money(data.outstanding_liability)}</strong>
            </div>
            <p className="text-sm text-neutral-600">
                {label('month_elapsed_percent')}:{' '}
                {textValue(data.month_elapsed_percent)}%
            </p>
            <section className="space-y-3">
                <h2 className="text-lg font-semibold">
                    {label('branch_ranking')}
                </h2>
                <DataTable
                    data={rows(data.branch_ranking)}
                    columns={[
                        'branch_id',
                        'cash_today',
                        'type',
                        'target_amount',
                        'achieved_amount',
                        'percent',
                        'month_elapsed_pct',
                    ]}
                />
            </section>
            <section>
                <h2 className="mb-3 text-lg font-semibold">
                    {label('attention')}
                </h2>
                <ul className="divide-y divide-neutral-200">
                    {rows(data.attention).map((item: Row, i) => (
                        <li
                            key={i}
                            className="flex flex-wrap items-center justify-between gap-3 py-4"
                        >
                            <div>
                                <span className="font-medium">
                                    {label(String(item.type))}
                                </span>
                                {item.branch_id !== undefined && (
                                    <span className="ml-2 text-sm">
                                        {label('branch_id')} #
                                        {textValue(item.branch_id)}
                                    </span>
                                )}
                                {item.count !== undefined && (
                                    <span className="ml-2 tabular-nums">
                                        {textValue(item.count)}
                                    </span>
                                )}
                                {item.amount !== undefined && (
                                    <span className="ml-2 font-semibold text-red-700">
                                        {money(item.amount)}
                                    </span>
                                )}
                            </div>
                            <Link
                                className="text-sm text-brand-700 underline underline-offset-4"
                                to={target(item.href)}
                            >
                                {w('Ko‘rib chiqish', 'Проверить', 'Review')}
                            </Link>
                        </li>
                    ))}
                </ul>
                {rows(data.attention).length === 0 && (
                    <p className="py-4 text-sm text-neutral-600">
                        {w(
                            'E’tibor talab qiladigan holat yo‘q.',
                            'Нет ситуаций, требующих внимания.',
                            'Nothing currently needs attention.',
                        )}
                    </p>
                )}
            </section>
        </div>
    )
}
