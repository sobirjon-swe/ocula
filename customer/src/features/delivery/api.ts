import { useMutation } from '@tanstack/react-query'

import { api, idempotencyKey } from '@/lib/api/client'
import type { DeliveryConfirmationResult, Envelope } from '@/lib/api/types'

export function useConfirmDelivery(stopId: number) {
  return useMutation({
    mutationFn: () =>
      api.post<Envelope<DeliveryConfirmationResult>>(`/customer/trip-stops/${stopId}/confirm`, {
        idempotencyKey: idempotencyKey(),
      }),
  })
}

export function useDisputeDelivery(stopId: number) {
  return useMutation({
    mutationFn: () =>
      api.post<Envelope<DeliveryConfirmationResult>>(`/customer/trip-stops/${stopId}/dispute`, {
        idempotencyKey: idempotencyKey(),
      }),
  })
}
