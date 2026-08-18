import { useSession } from '@/lib/session'
import type { Locale } from '@/lib/api/types'

import { messages } from './messages'
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
  return messages[sourceLocale(locale)][key]
}

export function useT(): (key: MessageKey) => string {
  const locale = useSession((state) => state.locale)

  return (key: MessageKey) => translate(locale, key)
}
