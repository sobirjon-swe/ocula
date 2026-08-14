import { useState } from 'react'
import { Navigate } from 'react-router-dom'

import { Button } from '@/components/ui/button'
import { Spinner } from '@/components/ui/feedback'
import { Input } from '@/components/ui/input'
import { ApiError } from '@/lib/api/client'
import { useT } from '@/lib/i18n'
import { useSession } from '@/lib/session'

import { useLogin } from './api'

export function LoginPage() {
  const t = useT()
  const token = useSession((state) => state.token)
  const login = useLogin()

  const [phone, setPhone] = useState('')
  const [password, setPassword] = useState('')

  if (token !== null) {
    return <Navigate to="/" replace />
  }

  const error = login.error instanceof ApiError ? login.error : null

  return (
    <div className="flex min-h-full items-center justify-center p-6">
      <div className="w-full max-w-sm">
        <div className="mb-8 text-center">
          <h1 className="text-2xl font-semibold tracking-tight">{t('login.title')}</h1>
          <p className="mt-1 text-sm text-neutral-500">{t('login.subtitle')}</p>
        </div>

        <form
          className="space-y-4 rounded-2xl bg-white p-6 ring-1 ring-neutral-200"
          onSubmit={(event) => {
            event.preventDefault()
            login.mutate({ phone, password })
          }}
        >
          <Input
            label={t('login.phone')}
            type="tel"
            inputMode="tel"
            autoComplete="username"
            autoFocus
            placeholder="+998 90 123 45 67"
            value={phone}
            onChange={(event) => setPhone(event.target.value)}
            error={error?.fieldError('phone')}
          />

          <Input
            label={t('login.password')}
            type="password"
            autoComplete="current-password"
            value={password}
            onChange={(event) => setPassword(event.target.value)}
            error={error?.fieldError('password')}
          />

          {/*
            Maydonga bog'lanmagan xato — "urinishlar tugadi", "hisob
            o'chirilgan" kabi. Matn backenddan tayyor holda keladi.
          */}
          {error !== null && Object.keys(error.errors).length === 0 && (
            <p className="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">{error.message}</p>
          )}

          <Button type="submit" className="w-full" disabled={login.isPending}>
            {login.isPending && <Spinner />}
            {login.isPending ? t('login.submitting') : t('login.submit')}
          </Button>
        </form>
      </div>
    </div>
  )
}
