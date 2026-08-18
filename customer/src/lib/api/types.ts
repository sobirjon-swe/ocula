/**
 * Backend javob formati — PROJECT.md §9, `App\Support\Http\ApiResponse`.
 *
 *     { "data": {...}, "meta": {...} }        // muvaffaqiyat
 *     { "message": "...", "errors": {...} }   // xato
 */

export type Envelope<T> = {
  data: T
  meta?: Record<string, unknown>
}

export type PageMeta = {
  current_page: number
  from: number | null
  last_page: number
  path: string
  per_page: number
  to: number | null
  total: number
}

export type Paginated<T> = {
  data: T[]
  meta: PageMeta
}

export type ListParams = {
  page?: number
  per_page?: number
}

export type Locale = 'uz-latn' | 'uz-cyrl' | 'ru' | 'en'

// ------------------------------------------------------------ Customer

export type Customer = {
  id: number
  name: string
  phone: string | null
  locale: Locale
  telegram_id: number | null
  debt_balance: string
  abandoned_orders_count: number
  first_visit_at: string | null
}

export type TelegramAuthResult = {
  token: string
  customer: Customer
}

// ----------------------------------------------------------- Sales/Order

export type OrderStatus =
  | 'new'
  | 'awaiting_exam'
  | 'prescription_ready'
  | 'materials_reserved'
  | 'awaiting_transfer'
  | 'in_workshop'
  | 'ready'
  | 'customer_notified'
  | 'delivered'
  | 'closed'
  | 'cancelled'
  | 'returned'
  | 'rework'

export type PaymentStatus = 'unpaid' | 'partial' | 'paid' | 'debt' | 'refunded'

export type DeliveryType = 'pickup' | 'courier'

export type Order = {
  id: number
  number: string
  status: OrderStatus
  payment_status: PaymentStatus
  total: string
  paid: string
  debt: string
  due_date: string | null
  delivery_type: DeliveryType
  delivered_at: string | null
  created_at: string
}

// -------------------------------------------------------- Prescription

export type EyeParams = {
  sph: string | null
  cyl: string | null
  axis: number | null
  add: string | null
}

export type Prescription = {
  id: number
  branch: string
  doctor: string
  od: EyeParams
  os: EyeParams
  pd: string | null
  pd_near: string | null
  prism: string | null
  notes: string | null
  valid_until: string | null
  is_expired: boolean
  created_at: string
}

// -------------------------------------------------------------- Debt

export type Debt = {
  debt_balance: string
  overdue_orders_count: number
}

// --------------------------------------------------------- Delivery

export type ConfirmationStatus = 'awaiting' | 'confirmed' | 'disputed' | 'unconfirmed'

export type DeliveryConfirmationResult = {
  confirmation_status: ConfirmationStatus
}
