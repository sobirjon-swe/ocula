import { Card, ErrorBlock, LoadingBlock } from '@/components/ui/feedback'
import { useT } from '@/lib/i18n'
import { formatMoney } from '@/lib/utils'

import { useDebt } from './api'

export function DebtPage() {
  const t = useT()
  const debt = useDebt()

  return (
    <div className="space-y-4 p-4 pb-24">
      <h1 className="text-xl font-semibold text-neutral-950">{t('debt.title')}</h1>

      {debt.isPending && <LoadingBlock label={t('app.loading')} />}
      {debt.isError && <ErrorBlock error={debt.error} onRetry={() => debt.refetch()} />}

      {debt.data !== undefined && (
        <>
          <Card>
            <div className="text-sm text-neutral-500">{t('debt.balance')}</div>
            {Number.parseFloat(debt.data.debt_balance) > 0 ? (
              <div className="mt-1 text-2xl font-semibold text-red-700">
                {formatMoney(debt.data.debt_balance)}
              </div>
            ) : (
              <div className="mt-1 text-sm text-neutral-500">{t('debt.none')}</div>
            )}
          </Card>

          {debt.data.overdue_orders_count > 0 && (
            <Card>
              <div className="text-sm text-neutral-500">{t('debt.overdueCount')}</div>
              <div className="mt-1 text-2xl font-semibold text-neutral-950">
                {debt.data.overdue_orders_count}
              </div>
            </Card>
          )}
        </>
      )}
    </div>
  )
}
