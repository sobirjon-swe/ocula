import { keepPreviousData, useQuery } from '@tanstack/react-query'

import { api } from '@/lib/api/client'
import type { Branch, ListParams, Paginated, StockBalance } from '@/lib/api/types'

/**
 * Qoldiqlar — kesh jadvalidan (7.1). Nol va manfiy qatorlar backendda
 * `available()` scope bilan chiqarib tashlangan.
 */
export function useStockBalances(params: ListParams) {
  return useQuery({
    queryKey: ['stock', 'balances', params],
    queryFn: () => api.get<Paginated<StockBalance>>('/stock/balances', { params }),
    placeholderData: keepPreviousData,
  })
}

/** Filial filtri uchun. Xodim faqat o'ziga ruxsat berilgan filiallarni ko'radi. */
export function useBranches() {
  return useQuery({
    queryKey: ['branches'],
    queryFn: () => api.get<Paginated<Branch>>('/branches', { params: { per_page: 100 } }),
    staleTime: 5 * 60_000,
  })
}
