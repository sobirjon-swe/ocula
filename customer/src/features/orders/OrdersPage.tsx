import { useState } from 'react'
import { ChevronRight } from 'lucide-react'
import { Link } from 'react-router-dom'

import { Card, ErrorBlock, LoadingBlock } from '@/components/ui/feedback'
import { useT } from '@/lib/i18n'
import { formatDate, formatMoney } from '@/lib/utils'

import { useOrders } from './api'
import { OrderStatusBadge, PaymentStatusBadge } from './StatusBadge'

export function OrdersPage() {
  const t = useT()
  const [page,setPage] = useState(1)
  const orders = useOrders(page)

  return (
    <div className="space-y-4 p-4 pb-24">
      <h1 className="text-xl font-semibold text-neutral-950">{t('orders.title')}</h1>

      {orders.isPending && <LoadingBlock label={t('app.loading')} />}
      {orders.isError && <ErrorBlock error={orders.error} onRetry={() => orders.refetch()} />}
      {orders.data !== undefined && orders.data.data.length === 0 && (
        <p className="text-sm text-neutral-500">{t('orders.empty')}</p>
      )}

      <div className="space-y-3">
        {orders.data?.data.map((order) => (
          <Link key={order.id} to={`/orders/${order.id}`}>
            <Card className="flex items-center justify-between">
              <div className="space-y-1.5">
                <div className="font-medium text-neutral-950">{order.number}</div>
                <div className="flex gap-2">
                  <OrderStatusBadge status={order.status} />
                  <PaymentStatusBadge status={order.payment_status} />
                </div>
                <div className="text-sm text-neutral-500">
                  {formatMoney(order.total)} · {formatDate(order.created_at)}
                </div>
              </div>
              <ChevronRight className="h-5 w-5 shrink-0 text-neutral-400" aria-hidden />
            </Card>
          </Link>
        ))}
      </div>
      <nav aria-label="Pagination" className="flex justify-between gap-4"><button className="min-h-11 px-3 disabled:opacity-40" disabled={page===1} onClick={()=>setPage(page-1)}>{t('page.previous')}</button><span className="py-3">{page}</span><button className="min-h-11 px-3 disabled:opacity-40" disabled={orders.data===undefined || page>=(orders.data?.meta.last_page??1)} onClick={()=>setPage(page+1)}>{t('page.next')}</button></nav>
    </div>
  )
}
