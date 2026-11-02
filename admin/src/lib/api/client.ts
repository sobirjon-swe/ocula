import { i18n } from '@/lib/i18n/runtime'
import { cyrillic } from '@/lib/i18n/transliterate'
import type { ListParams } from './types'

const BASE_URL =
    (import.meta.env.VITE_API_URL as string | undefined) ?? '/api/v1'

/**
 * Backend xatosi. `message` — foydalanuvchiga ko'rsatiladigan tayyor matn:
 * backend uni tanlangan tilda va "nima qilish kerak" ohangida yuboradi
 * (PROJECT.md §10), shuning uchun frontend uni qayta yozmaydi.
 */
export class ApiError extends Error {
    readonly status: number
    readonly errors: Record<string, string[]>

    constructor(
        status: number,
        message: string,
        errors: Record<string, string[]> = {},
    ) {
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

function clientMessage(key: string): string {
    const locale = context.locale()
    const value = String(
        i18n.t(key, { lng: locale === 'uz-cyrl' ? 'uz-latn' : locale }),
    )
    return locale === 'uz-cyrl' ? cyrillic(value) : value
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
            for (const [name, item] of Object.entries(
                value as Record<string, unknown>,
            )) {
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

async function request<T>(
    method: string,
    path: string,
    options: RequestOptions = {},
): Promise<T> {
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
        response = await fetch(
            `${BASE_URL}${path}${buildQuery(options.params)}`,
            {
                method,
                headers,
                body:
                    options.body === undefined
                        ? undefined
                        : JSON.stringify(options.body),
                signal: options.signal,
            },
        )
    } catch {
        // Tarmoq uzildi — filialda bu odatiy hol, xabar shunga yarasha.
        throw new ApiError(0, clientMessage('network.unavailable'), {})
    }

    if (response.status === 401) {
        context.onUnauthenticated()
        throw new ApiError(401, clientMessage('auth.expired'), {})
    }

    if (response.status === 204) {
        return null as T
    }

    const payload: unknown = await response.json().catch(() => null)

    if (!response.ok) {
        const body = (payload ?? {}) as {
            message?: string
            errors?: Record<string, string[]>
        }

        throw new ApiError(
            response.status,
            body.message ??
                clientMessage('request.failed') + ` (${response.status})`,
            body.errors ?? {},
        )
    }

    return payload as T
}

export const api = {
    get: <T>(path: string, options?: RequestOptions) =>
        request<T>('GET', path, options),
    post: <T>(path: string, options?: RequestOptions) =>
        request<T>('POST', path, options),
    put: <T>(path: string, options?: RequestOptions) =>
        request<T>('PUT', path, options),
    patch: <T>(path: string, options?: RequestOptions) =>
        request<T>('PATCH', path, options),
    delete: <T>(path: string, options?: RequestOptions) =>
        request<T>('DELETE', path, options),
}

/** `Idempotency-Key` uchun UUID. */
export function idempotencyKey(): string {
    return crypto.randomUUID()
}

/** Authenticated exports stay on the existing API transport; URLs carry no token. */
export async function fetchBlob(
    path: string,
    params: Record<string, unknown> = {},
): Promise<Blob> {
    const headers: Record<string, string> = {
        Accept: 'application/octet-stream',
        'Accept-Language': context.locale(),
    }
    const token = context.token()
    if (token) headers.Authorization = 'Bearer ' + token
    let response: Response
    try {
        response = await fetch(BASE_URL + path + buildQuery(params), {
            headers,
        })
    } catch {
        throw new ApiError(0, clientMessage('network.unavailable'))
    }
    if (response.status === 401) context.onUnauthenticated()
    if (!response.ok) {
        const body = (await response.json().catch(() => ({}))) as {
            message?: string
            errors?: Record<string, string[]>
        }
        throw new ApiError(
            response.status,
            body.message ?? clientMessage('export.failed'),
            body.errors,
        )
    }
    return response.blob()
}
export async function downloadFile(
    path: string,
    filename: string,
    params: Record<string, unknown> = {},
): Promise<void> {
    const url = URL.createObjectURL(await fetchBlob(path, params)),
        link = document.createElement('a')
    link.href = url
    link.download = filename
    link.click()
    setTimeout(() => URL.revokeObjectURL(url), 1000)
}
