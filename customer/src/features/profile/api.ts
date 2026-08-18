import { useMutation } from '@tanstack/react-query'

import { api } from '@/lib/api/client'
import type { ApiError } from '@/lib/api/client'
import type { Customer, Envelope, Locale } from '@/lib/api/types'
import { useSession } from '@/lib/session'

type Input = { name?: string; phone?: string; locale?: Locale }

export function useUpdateProfile() {
  const setCustomer = useSession((state) => state.setCustomer)

  return useMutation<Envelope<Customer>, ApiError, Input>({
    mutationFn: (input) => api.put<Envelope<Customer>>('/customer/profile', { body: input }),
    onSuccess: (response) => setCustomer(response.data),
  })
}
