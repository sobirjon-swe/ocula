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

/** Laravel resource collection'ining sahifalash bloki. */
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

/** So'rov parametrlari. `filter[...]` va `sort` — spatie/laravel-query-builder. */
export type ListParams = {
  page?: number
  per_page?: number
  sort?: string
  search?: string
  filter?: Record<string, string | number | boolean | undefined>
}

// ---------------------------------------------------------------- Core

export type Locale = 'uz-latn' | 'uz-cyrl' | 'ru' | 'en'

export type Branch = {
  id: number
  name: string
  code: string
  type: string
  address: string | null
  phone: string | null
  open_time: string | null
  close_time: string | null
  is_active: boolean
  users_count?: number
  created_at: string | null
}

export type User = {
  id: number
  name: string
  phone: string
  email: string | null
  branch_id: number | null
  branch?: Branch | null
  locale: Locale
  is_active: boolean
  has_pin: boolean
  debt_limit?: string
  roles?: string[]
  last_login_at: string | null
  created_at: string | null
}

export type LoginResult = {
  token: string
  user: User
  permissions: string[]
}

// ------------------------------------------------------------- Catalog

export type Brand = { id: number; name: string; country?: string | null }
export type Category = { id: number; name: string; parent_id: number | null }

export type ProductStatus = 'pending' | 'approved' | 'rejected'

export type Product = {
  id: number
  type: string
  name: string
  brand_id: number | null
  brand?: Brand | null
  category_id: number | null
  category?: Category | null
  unit: string
  status: ProductStatus
  quick_created: boolean
  merged_into_id: number | null
  is_active: boolean
  approved_at: string | null
  variants?: ProductVariant[]
  variants_count?: number
  created_at: string | null
}

export type ProductVariant = {
  id: number
  product_id: number
  sku: string
  barcode: string | null
  attributes: Record<string, unknown> | null
  sph: string | null
  cyl: string | null
  axis: number | null
  add: string | null
  index: string | null
  coating: string | null
  diameter: number | null
  color: string | null
  size: string | null
  optical_label: string | null
  is_active: boolean
  product?: Product
}

// ----------------------------------------------------------- Warehouse

export type StockBalance = {
  location_id: number
  variant_id: number
  branch_id: number
  quantity: number
  variant?: ProductVariant
}
