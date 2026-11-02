import { useTranslation } from 'react-i18next'
import { useSession } from '@/lib/session'
import type { Locale } from '@/lib/api/types'
import { i18n } from '@/lib/i18n/runtime'
import { cyrillic } from '@/lib/i18n/transliterate'
export { cyrillic }
export function words(
    locale: Locale,
    uz: string,
    ru: string,
    en: string,
): string {
    const source = locale === 'uz-cyrl' ? 'uz-latn' : locale
    const fallback = source === 'en' ? en : source === 'ru' ? ru : uz
    const value = String(i18n.t(en, { lng: source, defaultValue: fallback }))
    return locale === 'uz-cyrl' ? cyrillic(value) : value
}
export function useWords() {
    useTranslation()
    const locale = useSession((s) => s.locale)
    return (uz: string, ru: string, en: string) => words(locale, uz, ru, en)
}
export function useLabel() {
    useTranslation()
    const locale = useSession((s) => s.locale)
    return (key: string) => {
        const value = String(
            i18n.t('field:' + key, {
                lng: locale === 'uz-cyrl' ? 'uz-latn' : locale,
                defaultValue: key.toUpperCase().replaceAll('_', ' '),
            }),
        )
        return locale === 'uz-cyrl' ? cyrillic(value) : value
    }
}
