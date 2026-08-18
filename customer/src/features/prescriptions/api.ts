import { useQuery } from '@tanstack/react-query'

import { api } from '@/lib/api/client'
import type { Paginated, Prescription } from '@/lib/api/types'

export function usePrescriptions() {
  return useQuery({
    queryKey: ['customer', 'prescriptions'],
    queryFn: () =>
      api.get<Paginated<Prescription>>('/customer/prescriptions', { params: { per_page: 50 } }),
  })
}
