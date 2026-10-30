import type { ListParams } from './types'
import { toCyrillic } from '@/lib/i18n/transliterate'

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

  fieldError(field: string): string | undefined {
    return this.errors[field]?.[0]
  }
}

/**
 * Har so'rovga qo'shiladigan kontekst. Store'ga bog'lanib qolmaslik uchun
 * (aylanma import) qiymatlarni tashqaridan **funksiya** ko'rinishida oladi —
 * `lib/session.ts` ilova ko'tarilganda ulaydi.
 */
type Context = {
  token: () => string | null
  locale: () => string
  onUnauthenticated: () => void
}

function localError(kind:'network'|'session'|'request'):string {
 const source:Record<string,Record<string,string>>={network:{'uz-latn':"Serverga ulanib bo'lmadi. Internetni tekshiring va qayta urinib ko'ring.",ru:'Нет связи с сервером. Проверьте подключение и повторите попытку.',en:'Could not connect. Check your connection and try again.'},session:{'uz-latn':"Sessiya tugadi. Ilovani qayta oching.",ru:'Сессия истекла. Откройте приложение заново.',en:'Your session expired. Reopen the app.'},request:{'uz-latn':"So'rov bajarilmadi. Qayta urinib ko'ring.",ru:'Не удалось выполнить запрос. Повторите попытку.',en:'Could not complete the request. Please try again.'}}
 const locale=context.locale();const text=source[kind][locale==='uz-cyrl'?'uz-latn':locale]??source[kind].en
 return locale==='uz-cyrl'?toCyrillic(text):text
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
  params?: ListParams
  body?: unknown
  /**
   * Yetkazishni tasdiqlash/e'tiroz uchun majburiy — takroriy yuborish
   * ikkinchi hodisa yaratmasin (`EnsureIdempotency`, PROJECT.md §9).
   */
  idempotencyKey?: string
  signal?: AbortSignal
  /** 401 kelganda `onUnauthenticated` chaqirilmasin — auth so'rovining o'zi uchun. */
  skipAuthRedirect?: boolean
}

function buildQuery(params: RequestOptions['params']): string {
  if (!params) return ''

  const search = new URLSearchParams()

  for (const [key, value] of Object.entries(params)) {
    if (value === undefined || value === null) continue
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

  const controller = new AbortController()
  const abort = () => controller.abort()
  const timeout = window.setTimeout(abort, 20000)
  options.signal?.addEventListener('abort', abort, { once: true })
  if (options.signal?.aborted) controller.abort()
  let response: Response

  try {
    response = await fetch(`${BASE_URL}${path}${buildQuery(options.params)}`, {
      method,
      cache: 'no-store',
      headers,
      body: options.body === undefined ? undefined : JSON.stringify(options.body),
      signal: controller.signal,
    })
  } catch {
    // Tarmoq uzildi — mobil internetda bu odatiy hol, xabar shunga yarasha.
    throw new ApiError(0, localError('network'), {})
  } finally {
    window.clearTimeout(timeout)
    options.signal?.removeEventListener('abort', abort)
  }

  if (response.status === 401) {
    if (options.skipAuthRedirect !== true) {
      context.onUnauthenticated()
    }
    throw new ApiError(401, localError('session'), {})
  }

  if (response.status === 204) {
    return null as T
  }

  const payload: unknown = await response.json().catch(() => null)

  if (!response.ok) {
    const body = (payload ?? {}) as { message?: string; errors?: Record<string, string[]> }

    throw new ApiError(response.status, body.message ?? localError('request'), body.errors ?? {})
  }

  return payload as T
}

export const api = {
  get: <T>(path: string, options?: RequestOptions) => request<T>('GET', path, options),
  post: <T>(path: string, options?: RequestOptions) => request<T>('POST', path, options),
  put: <T>(path: string, options?: RequestOptions) => request<T>('PUT', path, options),
}

/** `Idempotency-Key` uchun UUID. */
export function idempotencyKey(): string {
  return crypto.randomUUID()
}
