import { toCyrillic } from './transliterate'
import i18next from 'i18next'
import { initReactI18next, useTranslation } from 'react-i18next'
import { useSession } from '@/lib/session'
import type { Locale } from '@/lib/api/types'

import { messages } from './messages'
import type { MessageKey, TranslatedLocale } from './messages'

export type { MessageKey, TranslatedLocale } from './messages'
export { messages } from './messages'

const cyrillic = Object.fromEntries(Object.entries(messages['uz-latn']).map(([key, value]) => [key, toCyrillic(value)]))
void i18next.use(initReactI18next).init({
  resources: {
    'uz-latn': { translation: messages['uz-latn'] },
    'uz-cyrl': { translation: cyrillic },
    ru: { translation: messages.ru },
    en: { translation: messages.en },
  },
  lng: useSession.getState().locale,
  fallbackLng: 'uz-latn',
  keySeparator: false,
  interpolation: { escapeValue: false },
  initAsync: false,
})
useSession.subscribe((next, previous) => {
  if (next.locale !== previous.locale) void i18next.changeLanguage(next.locale)
})

/** All four languages; Cyrillic is derived from the Latin source. */
export const LOCALE_OPTIONS: { value: Locale; label: string }[] = [
  { value: 'uz-latn', label: "O'zbekcha" },
  { value: 'uz-cyrl', label: 'Ўзбекча' },
  { value: 'ru', label: 'Русский' },
  { value: 'en', label: 'English' },
]

/** Tarjima fayli bor tilga keltirish (`uz-cyrl` → `uz-latn`). */
export function sourceLocale(locale: Locale): TranslatedLocale {
  return locale === 'uz-cyrl' ? 'uz-latn' : locale
}

export function translate(locale: Locale, key: MessageKey): string {
  const value = messages[sourceLocale(locale)][key]
  return locale === 'uz-cyrl' ? toCyrillic(value) : value
}

export function useT(): (key: MessageKey) => string {
  const { t } = useTranslation()
  return (key: MessageKey) => t(key)
}
