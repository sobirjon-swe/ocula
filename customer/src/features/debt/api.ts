import { useQuery } from '@tanstack/react-query'

import { api } from '@/lib/api/client'
import type { Debt, Envelope } from '@/lib/api/types'

export function useDebt() {
  return useQuery({
    queryKey: ['customer', 'debt'],
    queryFn: () => api.get<Envelope<Debt>>('/customer/debt').then((response) => response.data),
  })
}
