import { ChevronRight } from 'lucide-react'
import type { ReactNode } from 'react'
import { Link } from 'react-router-dom'

import { Card, ErrorBlock, LoadingBlock } from '@/components/ui/feedback'
import { useT } from '@/lib/i18n'
import { useSession } from '@/lib/session'
import { formatMoney } from '@/lib/utils'

import { activeOrder, useOrders } from '@/features/orders/api'
import { OrderStatusBadge, PaymentStatusBadge } from '@/features/orders/StatusBadge'
import { usePrescriptions } from '@/features/prescriptions/api'
import { useDebt } from '@/features/debt/api'

/**
 * Bosh sahifa — PROJECT.md §10: "Bir ekranda: faol buyurtma holati →
 * retseptim → qarzim → tarix". To'rttasi ham shu tartibda, bitta
 * scroll'da — alohida tablar emas.
 */
export function HomePage() {
  const t = useT()
  const customer = useSession((state) => state.customer)
  const orders = useOrders()
  const prescriptions = usePrescriptions()
  const debt = useDebt()

  return (
    <div className="space-y-6 p-4 pb-24">
      <h1 className="text-xl font-semibold text-neutral-950">
        {t('home.title')}
        {customer !== null && customer.name !== '' ? `, ${customer.name}` : ''}
      </h1>

      <Section title={t('home.activeOrder')}>
        {orders.isPending && <LoadingBlock label={t('app.loading')} />}
        {orders.isError && <ErrorBlock error={orders.error} onRetry={() => orders.refetch()} />}
        {orders.data !== undefined &&
          (() => {
            const order = activeOrder(orders.data.data)

            if (order === null) {
              return <p className="text-sm text-neutral-500">{t('home.noActiveOrder')}</p>
            }

            return (
              <Link to={`/orders/${order.id}`}>
                <Card className="flex items-center justify-between">
                  <div>
                    <div className="font-medium text-neutral-950">{order.number}</div>
                    <div className="mt-1 flex gap-2">
                      <OrderStatusBadge status={order.status} />
                      <PaymentStatusBadge status={order.payment_status} />
                    </div>
                  </div>
                  <ChevronRight className="h-5 w-5 text-neutral-400" aria-hidden />
                </Card>
              </Link>
            )
          })()}
      </Section>

      <Section title={t('home.prescriptions')}>
        {prescriptions.isPending && <LoadingBlock label={t('app.loading')} />}
        {prescriptions.isError && (
          <ErrorBlock error={prescriptions.error} onRetry={() => prescriptions.refetch()} />
        )}
        {prescriptions.data !== undefined && prescriptions.data.data.length === 0 && (
          <p className="text-sm text-neutral-500">{t('home.noPrescriptions')}</p>
        )}
        {prescriptions.data !== undefined && prescriptions.data.data.length > 0 && (
          <Link to="/prescriptions" className="flex items-center justify-between text-sm text-brand-700">
            {t('home.viewAll')}
            <ChevronRight className="h-4 w-4" aria-hidden />
          </Link>
        )}
      </Section>

      <Section title={t('home.debt')}>
        {debt.isPending && <LoadingBlock label={t('app.loading')} />}
        {debt.isError && <ErrorBlock error={debt.error} onRetry={() => debt.refetch()} />}
        {debt.data !== undefined && (
          <Card>
            {Number.parseFloat(debt.data.debt_balance) > 0 ? (
              <p className="text-lg font-semibold text-red-700">{formatMoney(debt.data.debt_balance)}</p>
            ) : (
              <p className="text-sm text-neutral-500">{t('home.noDebt')}</p>
            )}
          </Card>
        )}
      </Section>

      <Link
        to="/orders"
        className="flex items-center justify-between rounded-2xl bg-white p-4 text-sm font-medium text-neutral-700 ring-1 ring-neutral-200"
      >
        {t('home.history')}
        <ChevronRight className="h-4 w-4 text-neutral-400" aria-hidden />
      </Link>
    </div>
  )
}

function Section({ title, children }: { title: string; children: ReactNode }) {
  return (
    <section className="space-y-2">
      <h2 className="text-sm font-medium text-neutral-500">{title}</h2>
      {children}
    </section>
  )
}
