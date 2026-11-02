import { useEffect, useState } from 'react'
import { api, idempotencyKey } from '@/lib/api/client'
import { useSession } from '@/lib/session'
import { rows, type Row } from './shared'
export function useAutomaticLostSale(
    search: string,
    data: unknown,
    settled: boolean,
    customerId: number | null,
) {
    const user = useSession((s) => s.user),
        allowed = useSession((s) =>
            s.permissions.includes('warehouse.lost_sale.create'),
        ),
        [error, setError] = useState<unknown>(null)
    useEffect(() => {
        const term = search.trim()
        if (!allowed || !user?.branch_id || term.length < 3 || !settled) return
        const matches = rows(data)
        const missing = matches.filter(
            (row) =>
                Array.isArray(row.branch_balances) &&
                rows(row.branch_balances)
                    .filter((b) => Number(b.branch_id) === user.branch_id)
                    .reduce((sum, b) => sum + Number(b.quantity), 0) === 0,
        )
        const events: { variant: Row | null; reason: string }[] =
            matches.length === 0
                ? [{ variant: null, reason: 'not_in_catalog' }]
                : missing.map((variant) => ({
                      variant,
                      reason: 'out_of_stock',
                  }))
        for (const event of events) {
            const key =
                'optika.lost-search.' +
                user.id +
                '.' +
                user.branch_id +
                '.' +
                (event.variant?.id ?? term.toLocaleLowerCase())
            const previous = Number(sessionStorage.getItem(key) ?? 0)
            if (Date.now() - previous < 120000) continue
            sessionStorage.setItem(key, String(Date.now()))
            void api
                .post('/lost-sales', {
                    idempotencyKey: idempotencyKey(),
                    body: {
                        branch_id: user.branch_id,
                        customer_id: customerId,
                        search_term: term.slice(0, 160),
                        variant_id: event.variant?.id ?? null,
                        reason: event.reason,
                    },
                })
                .then(() => setError(null))
                .catch((error) => {
                    sessionStorage.removeItem(key)
                    setError(error)
                })
        }
    }, [search, data, settled, allowed, user?.branch_id, user?.id, customerId])
    return error
}
