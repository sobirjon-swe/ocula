/**
 * Telegram Mini App bilan ishlash — rasmiy `telegram-web-app.js`
 * ustidan yupqa qatlam. Alohida SDK paketi ataylab ishlatilmagan
 * (loyihada hech qanday Telegram Mini App SDK'si tanlanmagan edi) —
 * `window.Telegram.WebApp` to'g'ridan-to'g'ri, `index.html` dagi rasmiy
 * skriptdan keladi.
 *
 * Ilova brauzerda (Telegramdan tashqarida) ochilsa `window.Telegram`
 * yo'q — bu holatda kirish mumkin emas, chunki backendda hozircha
 * boshqa autentifikatsiya yo'li yo'q (BOSQICH-9). `isAvailable()` shuni
 * tekshiradi.
 */

type TelegramWebApp = {
  initData: string
  initDataUnsafe: { start_param?: string }
  ready: () => void
  expand: () => void
  colorScheme: 'light' | 'dark'
  themeParams: Record<string, string | undefined>
  close: () => void
}

declare global {
  interface Window {
    Telegram?: { WebApp?: TelegramWebApp }
  }
}

function webApp(): TelegramWebApp | null {
  return window.Telegram?.WebApp ?? null
}

export function isAvailable(): boolean {
  return webApp() !== null
}

/** Ilova tayyor — Telegramga "yuklandim" deb bildiradi va butun ekranga yoyadi. */
export function bootstrap(): void {
  const app = webApp()
  app?.ready()
  app?.expand()
}

/**
 * Xom, imzolangan initData qatori — **o'zgartirmasdan** backendga
 * yuboriladi (`InitDataValidator` shu qatorning o'zini tekshiradi).
 */
export function getInitData(): string | null {
  const data = webApp()?.initData

  return data === undefined || data === '' ? null : data
}

/**
 * Bot havolasidagi `?startapp=...` parametri — masalan yetkazishni
 * tasdiqlash ekraniga to'g'ridan-to'g'ri o'tish uchun
 * (`t.me/bot/app?startapp=stop_123`).
 */
export function getStartParam(): string | null {
  return webApp()?.initDataUnsafe.start_param ?? null
}
