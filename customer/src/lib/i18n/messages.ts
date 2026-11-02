import uz from './locales/uz-latn.json'
import ru from './locales/ru.json'
import en from './locales/en.json'
export const messages = { 'uz-latn': uz, ru, en } as const
export type MessageKey = keyof typeof uz
export type TranslatedLocale = keyof typeof messages
