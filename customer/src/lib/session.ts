import { create } from 'zustand'
import { persist } from 'zustand/middleware'

import { configureApi } from '@/lib/api/client'
import type { Customer, Locale, TelegramAuthResult } from '@/lib/api/types'

/**
 * Sessiya — token va mijoz. Backend `POST /customer/auth/telegram` da
 * **Sanctum tokeni** qaytaradi (cookie emas — Mini App boshqa origin'da,
 * Telegram webview ichida), shuning uchun bu yerda saqlanadi va har
 * so'rovda `Authorization: Bearer` bilan yuboriladi (BOSQICH-9 §3).
 *
 * Kalit nomi admin/ dan ataylab boshqacha (`optika.customer.session`) —
 * ikkalasi bir domenda ishlab qolsa ham to'qnashmasin.
 */
type SessionState = {
  token: string | null
  customer: Customer | null
  locale: Locale

  signIn: (result: TelegramAuthResult) => void
  signOut: () => void
  setCustomer: (customer: Customer) => void
  setLocale: (locale: Locale) => void
}

export const useSession = create<SessionState>()(
  persist(
    (set) => ({
      token: null,
      customer: null,
      locale: 'uz-latn',

      signIn: (result) =>
        set({
          token: result.token,
          customer: result.customer,
          locale: result.customer.locale,
        }),

      signOut: () => set({ token: null, customer: null }),

      setCustomer: (customer) => set({ customer, locale: customer.locale }),

      setLocale: (locale) => set({ locale }),
    }),
    {
      name: 'optika.customer.session',
      partialize: (state) => ({
        token: state.token,
        customer: state.customer,
        locale: state.locale,
      }),
    },
  ),
)

// API klienti store'ni import qilmaydi (aylanma bog'lanish bo'lmasin) —
// kerakli qiymatlarni shu yerda ulab qo'yamiz.
configureApi({
  token: () => useSession.getState().token,
  locale: () => useSession.getState().locale,
  onUnauthenticated: () => useSession.getState().signOut(),
})
