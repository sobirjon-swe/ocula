import { Badge } from '@/components/ui/feedback'
import { useT } from '@/lib/i18n'
import type { PaymentStatus, OrderStatus } from '@/lib/api/types'

const ORDER_TONE: Record<OrderStatus, 'green' | 'amber' | 'red' | 'blue' | 'neutral'> = {
  new: 'neutral',
  awaiting_exam: 'blue',
  prescription_ready: 'blue',
  materials_reserved: 'blue',
  awaiting_transfer: 'blue',
  in_workshop: 'blue',
  ready: 'amber',
  customer_notified: 'amber',
  delivered: 'green',
  closed: 'green',
  cancelled: 'red',
  returned: 'red',
  rework: 'amber',
}

const PAYMENT_TONE: Record<PaymentStatus, 'green' | 'amber' | 'red' | 'blue' | 'neutral'> = {
  unpaid: 'red',
  partial: 'amber',
  paid: 'green',
  debt: 'amber',
  refunded: 'neutral',
}

/**
 * Holat va to'lov — ikkita **alohida** o'q (PROJECT.md 7.3): buyurtma
 * "tayyor" bo'lishi mumkin, lekin "qarzga" turishi ham mumkin — bittasi
 * ikkinchisini almashtirmaydi.
 */
export function OrderStatusBadge({ status }: { status: OrderStatus }) {
  const t = useT()

  return <Badge tone={ORDER_TONE[status]}>{t(`orderStatus.${status}`)}</Badge>
}

export function PaymentStatusBadge({ status }: { status: PaymentStatus }) {
  const t = useT()

  return <Badge tone={PAYMENT_TONE[status]}>{t(`paymentStatus.${status}`)}</Badge>
}
