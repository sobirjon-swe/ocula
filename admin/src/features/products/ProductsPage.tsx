import { useEffect, useState } from 'react'

import { Badge, ErrorBlock, LoadingBlock } from '@/components/ui/feedback'
import { Input } from '@/components/ui/input'
import { Pagination } from '@/components/ui/pagination'
import { EmptyRow, Table, Td, Th } from '@/components/ui/table'
import type { ProductStatus } from '@/lib/api/types'
import { useT } from '@/lib/i18n'
import type { MessageKey } from '@/lib/i18n'

import { useProducts } from './api'

const STATUS: Record<ProductStatus, { tone: 'green' | 'amber' | 'red'; label: MessageKey }> = {
  approved: { tone: 'green', label: 'status.approved' },
  pending: { tone: 'amber', label: 'status.pending' },
  rejected: { tone: 'red', label: 'status.rejected' },
}

export function ProductsPage() {
  const t = useT()
  const [search, setSearch] = useState('')
  const [debounced, setDebounced] = useState('')
  const [page, setPage] = useState(1)

  // Har harfda so'rov yubormaymiz — filialdagi internet buni ko'tarmaydi.
  useEffect(() => {
    const timer = setTimeout(() => {
      setDebounced(search)
      setPage(1)
    }, 300)

    return () => clearTimeout(timer)
  }, [search])

  const query = useProducts({ page, per_page: 25, search: debounced || undefined })

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap items-end justify-between gap-4">
        <h1 className="text-xl font-semibold tracking-tight">{t('products.title')}</h1>

        <Input
          type="search"
          placeholder={t('products.search')}
          value={search}
          onChange={(event) => setSearch(event.target.value)}
          className="w-72"
        />
      </div>

      {query.isPending && <LoadingBlock label={t('app.loading')} />}

      {query.isError && <ErrorBlock error={query.error} onRetry={() => void query.refetch()} retryLabel={t('error.retry')} />}

      {query.data !== undefined && (
        <>
          <Table>
            <thead>
              <tr>
                <Th>{t('products.name')}</Th>
                <Th>{t('products.brand')}</Th>
                <Th>{t('products.category')}</Th>
                <Th>{t('products.type')}</Th>
                <Th className="text-right">{t('products.variants')}</Th>
                <Th>{t('products.status')}</Th>
              </tr>
            </thead>

            <tbody>
              {query.data.data.length === 0 && (
                <EmptyRow colSpan={6}>{t('products.empty')}</EmptyRow>
              )}

              {query.data.data.map((product) => (
                <tr key={product.id} className="hover:bg-neutral-50">
                  <Td className="font-medium text-neutral-900">{product.name}</Td>
                  <Td>{product.brand?.name ?? '—'}</Td>
                  <Td>{product.category?.name ?? '—'}</Td>
                  <Td className="text-neutral-500">{product.type}</Td>
                  <Td className="text-right tabular-nums">{product.variants_count ?? 0}</Td>
                  <Td>
                    <Badge tone={STATUS[product.status].tone}>
                      {t(STATUS[product.status].label)}
                    </Badge>
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
