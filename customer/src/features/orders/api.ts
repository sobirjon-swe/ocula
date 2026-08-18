import { useQuery } from '@tanstack/react-query'

import { api } from '@/lib/api/client'
import type { Envelope, Order, Paginated } from '@/lib/api/types'

export function useOrders() {
  return useQuery({
    queryKey: ['customer', 'orders'],
    queryFn: () => api.get<Paginated<Order>>('/customer/orders', { params: { per_page: 50 } }),
  })
}

export function useOrder(id: number) {
  return useQuery({
    queryKey: ['customer', 'orders', id],
    queryFn: () => api.get<Envelope<Order>>(`/customer/orders/${id}`).then((response) => response.data),
  })
}

/** Bosh sahifada ko'rsatiladigan "faol" buyurtma — yopilmagan eng yangisi. */
export function activeOrder(orders: Order[]): Order | null {
  const closed: Order['status'][] = ['closed', 'cancelled', 'returned']

  return orders.find((order) => !closed.includes(order.status)) ?? null
}
