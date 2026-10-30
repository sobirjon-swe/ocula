import i18next from 'i18next'
import { initReactI18next } from 'react-i18next'
import { messages } from './messages'
export const i18n = i18next.createInstance()
void i18n
    .use(initReactI18next)
    .init({
        lng: 'uz-latn',
        fallbackLng: 'uz-latn',
        keySeparator: false,
        nsSeparator: false,
        interpolation: { escapeValue: false },
        resources: Object.fromEntries(
            Object.entries(messages).map(([locale, translation]) => [
                locale,
                { translation },
            ]),
        ),
    })
