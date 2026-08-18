import { ArrowLeft } from 'lucide-react'
import { Link, useParams } from 'react-router-dom'

import { Card, ErrorBlock, LoadingBlock } from '@/components/ui/feedback'
import { ApiError } from '@/lib/api/client'
import { useT } from '@/lib/i18n'
import { formatDate, formatMoney } from '@/lib/utils'

import { useOrder } from './api'
import { OrderStatusBadge, PaymentStatusBadge } from './StatusBadge'

export function OrderDetailPage() {
  const t = useT()
  const { id } = useParams<{ id: string }>()
  const order = useOrder(Number(id))

  return (
    <div className="space-y-4 p-4 pb-24">
      <Link to="/orders" className="inline-flex items-center gap-1.5 text-sm text-neutral-500">
        <ArrowLeft className="h-4 w-4" aria-hidden />
        {t('orders.title')}
      </Link>

      {order.isPending && <LoadingBlock label={t('app.loading')} />}

      {order.isError && (
        <ErrorBlock
          error={isNotFound(order.error) ? new Error(t('orders.notFound')) : order.error}
          onRetry={isNotFound(order.error) ? undefined : () => order.refetch()}
        />
      )}

      {order.data !== undefined && (
        <Card className="space-y-4">
          <div className="flex items-start justify-between">
            <h1 className="text-lg font-semibold text-neutral-950">{order.data.number}</h1>
            <div className="flex flex-col items-end gap-1.5">
              <OrderStatusBadge status={order.data.status} />
              <PaymentStatusBadge status={order.data.payment_status} />
            </div>
          </div>

          <dl className="space-y-2 text-sm">
            <Row label={t('orders.total')} value={formatMoney(order.data.total)} />
            <Row label={t('orders.paid')} value={formatMoney(order.data.paid)} />
            {Number.parseFloat(order.data.debt) > 0 && (
              <Row label={t('orders.debt')} value={formatMoney(order.data.debt)} emphasize />
            )}
            {order.data.due_date !== null && (
              <Row label={t('orders.dueDate')} value={formatDate(order.data.due_date)} />
            )}
            <Row label={t('orders.deliveryType')} value={t(`deliveryType.${order.data.delivery_type}`)} />
            <Row label={t('orders.createdAt')} value={formatDate(order.data.created_at)} />
          </dl>
        </Card>
      )}
    </div>
  )
}

function isNotFound(error: unknown): boolean {
  return error instanceof ApiError && error.status === 404
}

function Row({ label, value, emphasize }: { label: string; value: string; emphasize?: boolean }) {
  return (
    <div className="flex items-center justify-between">
      <dt className="text-neutral-500">{label}</dt>
      <dd className={emphasize === true ? 'font-semibold text-red-700' : 'font-medium text-neutral-900'}>
        {value}
      </dd>
    </div>
  )
}
