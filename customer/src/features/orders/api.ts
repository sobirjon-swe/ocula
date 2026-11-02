import { useQuery } from '@tanstack/react-query'

import { api } from '@/lib/api/client'
import type { Envelope, Order, Paginated } from '@/lib/api/types'

export function useOrders(page = 1, active = false) {
  return useQuery({
    queryKey: ['customer', 'orders', { page, active }],
    queryFn: () => api.get<Paginated<Order>>('/customer/orders', { params: { per_page: 20, page, active: active ? 1 : undefined } }),
  })
}

export function useOrder(id: number) {
  return useQuery({
    queryKey: ['customer', 'orders', id],
    enabled: Number.isSafeInteger(id) && id > 0,
    queryFn: () => api.get<Envelope<Order>>(`/customer/orders/${id}`).then((response) => response.data),
  })
}

/** Bosh sahifada ko'rsatiladigan "faol" buyurtma — yopilmagan eng yangisi. */
export function activeOrder(orders: Order[]): Order | null {
  const closed: Order['status'][] = ['delivered', 'closed', 'cancelled', 'returned']

  return orders.find((order) => !closed.includes(order.status)) ?? null
}
