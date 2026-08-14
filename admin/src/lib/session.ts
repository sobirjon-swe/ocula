import { create } from 'zustand'
import { persist } from 'zustand/middleware'

import { configureApi } from '@/lib/api/client'
import type { Locale, LoginResult, User } from '@/lib/api/types'

/**
 * Sessiya — token, xodim, uning ruxsatlari va tanlangan til.
 *
 * Backend `POST /auth/login` da **Sanctum tokeni** qaytaradi (cookie
 * sessiyasi emas), shuning uchun SPA tokenni saqlaydi va har so'rovda
 * `Authorization: Bearer` bilan yuboradi.
 */
type SessionState = {
  token: string | null
  user: User | null
  permissions: string[]
  locale: Locale

  signIn: (result: LoginResult) => void
  signOut: () => void
  setUser: (user: User, permissions: string[]) => void
  setLocale: (locale: Locale) => void
}

export const useSession = create<SessionState>()(
  persist(
    (set) => ({
      token: null,
      user: null,
      permissions: [],
      locale: 'uz-latn',

      signIn: (result) =>
        set({
          token: result.token,
          user: result.user,
          permissions: result.permissions,
          locale: result.user.locale,
        }),

      signOut: () => set({ token: null, user: null, permissions: [] }),

      setUser: (user, permissions) => set({ user, permissions }),

      setLocale: (locale) => set({ locale }),
    }),
    {
      name: 'optika.session',
      partialize: (state) => ({
        token: state.token,
        user: state.user,
        permissions: state.permissions,
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

/**
 * Ruxsat tekshiruvi — nomlar backenddagi bilan bir xil
 * (`catalog.product.view_any`, PERMISSIONS.md).
 *
 * Bu **qulaylik**, himoya emas: haqiqiy tekshiruv Policy'da, serverda.
 */
export function useCan(permission: string): boolean {
  return useSession((state) => state.permissions.includes(permission))
}

export function can(permission: string): boolean {
  return useSession.getState().permissions.includes(permission)
}
