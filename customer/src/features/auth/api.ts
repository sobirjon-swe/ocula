import { useMutation } from '@tanstack/react-query'

import { api } from '@/lib/api/client'
import type { Envelope, TelegramAuthResult } from '@/lib/api/types'
import { useSession } from '@/lib/session'

/**
 * Telegram `initData` ni Sanctum tokeniga almashtiradi — BOSQICH-9 §3.
 *
 * `skipAuthRedirect: true` — bu so'rovning o'zi hali tokensiz, 401
 * kelishi mumkin emas (yoki imzo yaroqsiz bo'lsa ham), lekin
 * `onUnauthenticated` shu yerda ishga tushmasin (aylanma emas).
 */
export function useTelegramAuth() {
  const signIn = useSession((state) => state.signIn)

  return useMutation({
    mutationFn: (initData: string) =>
      api.post<Envelope<TelegramAuthResult>>('/customer/auth/telegram', {
        body: { init_data: initData },
        skipAuthRedirect: true,
      }),
    onSuccess: (response) => signIn(response.data),
  })
}
