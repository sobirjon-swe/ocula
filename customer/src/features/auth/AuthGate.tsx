import { useEffect } from 'react'
import type { ReactNode } from 'react'

import { LoadingBlock } from '@/components/ui/feedback'
import { Button } from '@/components/ui/button'
import { useT } from '@/lib/i18n'
import { useSession } from '@/lib/session'
import { bootstrap, getInitData, isAvailable } from '@/lib/telegram'

import { useTelegramAuth } from './api'

/**
 * "Ro'yxatdan o'tish formasi YO'Q" (PROJECT.md §10) — Telegram kim
 * ekanini o'zi biladi. Ilova ochilganda, agar hali token bo'lmasa,
 * `initData` avtomatik almashtiriladi; foydalanuvchi hech narsa
 * bosmaydi, faqat xato bo'lsa qayta urinish tugmasini ko'radi.
 */
export function AuthGate({ children }: { children: ReactNode }) {
  const t = useT()
  const token = useSession((state) => state.token)
  const auth = useTelegramAuth()

  useEffect(() => {
    if (token !== null || !isAvailable()) return

    bootstrap()

    const initData = getInitData()

    if (initData !== null) {
      auth.mutate(initData)
    }
    // `auth.mutate` TanStack Query'dan — har render'da yangi referens,
    // lekin `token` o'zgarmaguncha effekt qayta ishga tushmaydi, shuning
    // uchun faqat shu bittasi qaramlik sifatida yetarli.
  }, [token])

  if (!isAvailable()) {
    return (
      <div className="flex min-h-full flex-col items-center justify-center gap-4 px-6 text-center">
        <p className="text-neutral-600">{t('auth.outsideTelegram')}</p>
      </div>
    )
  }

  if (token === null) {
    if (auth.isError) {
      return (
        <div className="flex min-h-full flex-col items-center justify-center gap-4 px-6 text-center">
          <p className="text-neutral-600">{t('auth.error')}</p>
          <Button
            onClick={() => {
              const initData = getInitData()
              if (initData !== null) auth.mutate(initData)
            }}
          >
            {t('auth.retry')}
          </Button>
        </div>
      )
    }

    return <LoadingBlock label={t('auth.connecting')} />
  }

  return children
}
