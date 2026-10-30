import { cyrillic } from './transliterate'
import { i18n } from './runtime'
import { useTranslation } from 'react-i18next'
import { useSession } from '@/lib/session'
import type { Locale } from '@/lib/api/types'

import type { MessageKey, TranslatedLocale } from './messages'

export type { MessageKey, TranslatedLocale } from './messages'
export { messages } from './messages'

/** Til tanlash ro'yxati — `uz-cyrl` yo'q, u backend hosilasi. */
export const LOCALE_OPTIONS: { value: TranslatedLocale; label: string }[] = [
    { value: 'uz-latn', label: "O'zbekcha" },
    { value: 'ru', label: 'Русский' },
    { value: 'en', label: 'English' },
]

/** Tarjima fayli bor tilga keltirish (`uz-cyrl` → `uz-latn`). */
export function sourceLocale(locale: Locale): TranslatedLocale {
    return locale === 'uz-cyrl' ? 'uz-latn' : locale
}

export function translate(locale: Locale, key: MessageKey): string {
    const value = String(i18n.t(key, { lng: sourceLocale(locale) }))
    return locale === 'uz-cyrl' ? cyrillic(value) : value
}

export function useT(): (key: MessageKey) => string {
    useTranslation()
    const locale = useSession((state) => state.locale)

    return (key: MessageKey) => translate(locale, key)
}
