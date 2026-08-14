import { keepPreviousData, useQuery } from '@tanstack/react-query'

import { api } from '@/lib/api/client'
import type { ListParams, Paginated, Product } from '@/lib/api/types'

/**
 * Tovarlar ro'yxati.
 *
 * `search` — oddiy filtr emas: backend uni pg_trgm o'xshashligi bo'yicha
 * **tartiblab** ham beradi (7.13), shuning uchun `filter[]` dan tashqarida
 * yuboriladi.
 */
export function useProducts(params: ListParams) {
  return useQuery({
    queryKey: ['products', params],
    queryFn: () => api.get<Paginated<Product>>('/products', { params }),
    // Sahifa almashganda jadval bo'shab ketmasin.
    placeholderData: keepPreviousData,
  })
}
