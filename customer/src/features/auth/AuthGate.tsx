import { useEffect, useRef } from 'react'
import { useQuery } from '@tanstack/react-query'
import { api } from '@/lib/api/client'
import type { Customer, Envelope } from '@/lib/api/types'
import type { ReactNode } from 'react'

import { LoadingBlock } from '@/components/ui/feedback'
import { Button } from '@/components/ui/button'
import { useT } from '@/lib/i18n'
import { useSession } from '@/lib/session'
import { bootstrap, getInitData } from '@/lib/telegram'

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
  const started = useRef(false)
  const initData = getInitData()
  const profile = useQuery({queryKey:['customer','identity',token], enabled:token!==null && initData===null, retry:false, queryFn:()=>api.get<Envelope<Customer>>('/customer/profile').then(r=>r.data)})
  useEffect(()=>{if(profile.data)useSession.getState().setCustomer(profile.data)},[profile.data])

  useEffect(() => {
    if (started.current || initData === null) return
    started.current = true
    bootstrap()
    auth.mutate(initData)
    // `auth.mutate` TanStack Query'dan — har render'da yangi referens,
    // lekin `token` o'zgarmaguncha effekt qayta ishga tushmaydi, shuning
    // uchun faqat shu bittasi qaramlik sifatida yetarli.
  }, [token, initData])

  if (initData === null && token === null) {
    return (
      <div className="flex min-h-full flex-col items-center justify-center gap-4 px-6 text-center">
        <p className="text-neutral-600">{t('auth.outsideTelegram')}</p>
      </div>
    )
  }

  if (token === null || (initData !== null && !auth.isSuccess)) {
    if (auth.isError || (auth.isSuccess && token === null)) {
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

  if (initData === null && profile.isError) return <div className="p-6"><p>{t('auth.error')}</p><Button onClick={()=>profile.refetch()}>{t('auth.retry')}</Button></div>
  if (initData === null && !profile.isSuccess) return <LoadingBlock label={t('auth.connecting')} />
  return children
}
