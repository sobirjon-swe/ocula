import { useState } from 'react'

import { ErrorBlock, LoadingBlock } from '@/components/ui/feedback'
import { Select } from '@/components/ui/input'
import { Pagination } from '@/components/ui/pagination'
import { EmptyRow, Table, Td, Th } from '@/components/ui/table'
import { useT } from '@/lib/i18n'
import { formatQuantity } from '@/lib/utils'

import { useBranches, useStockBalances } from './api'

export function StockPage() {
  const t = useT()
  const [branchId, setBranchId] = useState('')
  const [page, setPage] = useState(1)

  const branches = useBranches()
  const query = useStockBalances({
    page,
    per_page: 50,
    sort: '-quantity',
    filter: { branch_id: branchId || undefined },
  })

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap items-end justify-between gap-4">
        <h1 className="text-xl font-semibold tracking-tight">{t('stock.title')}</h1>

        <Select
          aria-label={t('stock.branch')}
          value={branchId}
          onChange={(event) => {
            setBranchId(event.target.value)
            setPage(1)
          }}
          className="w-56"
        >
          <option value="">{t('stock.allBranches')}</option>
          {branches.data?.data.map((branch) => (
            <option key={branch.id} value={branch.id}>
              {branch.name}
            </option>
          ))}
        </Select>
      </div>

      {query.isPending && <LoadingBlock label={t('app.loading')} />}

      {query.isError && (
        <ErrorBlock error={query.error} onRetry={() => void query.refetch()} retryLabel={t('error.retry')} />
      )}

      {query.data !== undefined && (
        <>
          <Table>
            <thead>
              <tr>
                <Th>{t('stock.sku')}</Th>
                <Th>{t('stock.optical')}</Th>
                <Th>{t('stock.barcode')}</Th>
                <Th>{t('stock.location')}</Th>
                <Th className="text-right">{t('stock.quantity')}</Th>
              </tr>
            </thead>

            <tbody>
              {query.data.data.length === 0 && <EmptyRow colSpan={5}>{t('stock.empty')}</EmptyRow>}

              {query.data.data.map((balance) => (
                <tr
                  key={`${balance.location_id}-${balance.variant_id}`}
                  className="hover:bg-neutral-50"
                >
                  <Td className="font-medium text-neutral-900">{balance.variant?.sku ?? '—'}</Td>
                  <Td className="text-neutral-600">{balance.variant?.optical_label ?? '—'}</Td>
                  <Td className="text-neutral-500 tabular-nums">
                    {balance.variant?.barcode ?? '—'}
                  </Td>
                  <Td className="text-neutral-500 tabular-nums">#{balance.location_id}</Td>
                  <Td className="text-right font-medium tabular-nums">
                    {formatQuantity(balance.quantity)}
                  </Td>
                </tr>
              ))}
            </tbody>
          </Table>

          <Pagination meta={query.data.meta} onChange={setPage} />
        </>
      )}
    </div>
  )
}
