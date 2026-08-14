import type { ListParams } from './types'

const BASE_URL = (import.meta.env.VITE_API_URL as string | undefined) ?? '/api/v1'

/**
 * Backend xatosi. `message` — foydalanuvchiga ko'rsatiladigan tayyor matn:
 * backend uni tanlangan tilda va "nima qilish kerak" ohangida yuboradi
 * (PROJECT.md §10), shuning uchun frontend uni qayta yozmaydi.
 */
export class ApiError extends Error {
  readonly status: number
  readonly errors: Record<string, string[]>

  constructor(status: number, message: string, errors: Record<string, string[]> = {}) {
    super(message)
    this.name = 'ApiError'
    this.status = status
    this.errors = errors
  }

  /** Maydon bo'yicha birinchi xato — forma inputlari ostiga chiqadi. */
  fieldError(field: string): string | undefined {
    return this.errors[field]?.[0]
  }
}

/**
 * Har so'rovga qo'shiladigan kontekst. Store'ga bog'lanib qolmaslik uchun
 * (aylanma import) qiymatlarni tashqaridan **funksiya** ko'rinishida oladi —
 * `auth/store.ts` ilova ko'tarilganda ulaydi.
 */
type Context = {
  token: () => string | null
  locale: () => string
  onUnauthenticated: () => void
}

let context: Context = {
  token: () => null,
  locale: () => 'uz-latn',
  onUnauthenticated: () => {},
}

export function configureApi(next: Context): void {
  context = next
}

type RequestOptions = {
  params?: ListParams | Record<string, unknown>
  body?: unknown
  /**
   * Ombor/kassa yozadigan endpointlar uchun majburiy — takroriy yuborish
   * dublikat hujjat yaratmasin (`EnsureIdempotency`, PROJECT.md §9).
   */
  idempotencyKey?: string
  signal?: AbortSignal
}

function buildQuery(params: RequestOptions['params']): string {
  if (!params) return ''

  const search = new URLSearchParams()

  for (const [key, value] of Object.entries(params)) {
    if (value === undefined || value === null || value === '') continue

    // `filter: { status: 'pending' }` → `filter[status]=pending`
    if (key === 'filter' && typeof value === 'object') {
      for (const [name, item] of Object.entries(value as Record<string, unknown>)) {
        if (item === undefined || item === null || item === '') continue
        search.set(`filter[${name}]`, String(item))
      }
      continue
    }

    search.set(key, String(value))
  }

  const query = search.toString()

  return query === '' ? '' : `?${query}`
}

async function request<T>(method: string, path: string, options: RequestOptions = {}): Promise<T> {
  const headers: Record<string, string> = {
    Accept: 'application/json',
    'Accept-Language': context.locale(),
  }

  const token = context.token()
  if (token !== null) {
    headers.Authorization = `Bearer ${token}`
  }

  if (options.body !== undefined) {
    headers['Content-Type'] = 'application/json'
  }

  if (options.idempotencyKey !== undefined) {
    headers['Idempotency-Key'] = options.idempotencyKey
  }

  let response: Response

  try {
    response = await fetch(`${BASE_URL}${path}${buildQuery(options.params)}`, {
      method,
      headers,
      body: options.body === undefined ? undefined : JSON.stringify(options.body),
      signal: options.signal,
    })
  } catch {
    // Tarmoq uzildi — filialda bu odatiy hol, xabar shunga yarasha.
    throw new ApiError(0, 'Serverga ulanib bo‘lmadi. Internetni tekshiring.', {})
  }

  if (response.status === 401) {
    context.onUnauthenticated()
    throw new ApiError(401, 'Sessiya tugadi. Qaytadan kiring.', {})
  }

  if (response.status === 204) {
    return null as T
  }

  const payload: unknown = await response.json().catch(() => null)

  if (!response.ok) {
    const body = (payload ?? {}) as { message?: string; errors?: Record<string, string[]> }

    throw new ApiError(
      response.status,
      body.message ?? `So‘rov bajarilmadi (${response.status}).`,
      body.errors ?? {},
    )
  }

  return payload as T
}

export const api = {
  get: <T>(path: string, options?: RequestOptions) => request<T>('GET', path, options),
  post: <T>(path: string, options?: RequestOptions) => request<T>('POST', path, options),
  put: <T>(path: string, options?: RequestOptions) => request<T>('PUT', path, options),
  patch: <T>(path: string, options?: RequestOptions) => request<T>('PATCH', path, options),
  delete: <T>(path: string, options?: RequestOptions) => request<T>('DELETE', path, options),
}

/** `Idempotency-Key` uchun UUID. */
export function idempotencyKey(): string {
  return crypto.randomUUID()
}
