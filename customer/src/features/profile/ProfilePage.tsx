import { useState } from 'react'
import type { FormEvent } from 'react'

import { Button } from '@/components/ui/button'
import { Input, Select } from '@/components/ui/input'
import { LOCALE_OPTIONS, useT } from '@/lib/i18n'
import type { TranslatedLocale } from '@/lib/i18n'
import { useSession } from '@/lib/session'
import type { Locale } from '@/lib/api/types'

import { useUpdateProfile } from './api'

export function ProfilePage() {
  const t = useT()
  const customer = useSession((state) => state.customer)
  const locale = useSession((state) => state.locale)
  const update = useUpdateProfile()

  const [name, setName] = useState(customer?.name ?? '')
  const [phone, setPhone] = useState(customer?.phone ?? '')
  const [selectedLocale, setSelectedLocale] = useState<TranslatedLocale>(
    locale === 'uz-cyrl' ? 'uz-latn' : locale,
  )

  function handleSubmit(event: FormEvent) {
    event.preventDefault()
    update.mutate({ name, phone: phone === '' ? undefined : phone, locale: selectedLocale as Locale })
  }

  return (
    <div className="space-y-4 p-4 pb-24">
      <h1 className="text-xl font-semibold text-neutral-950">{t('profile.title')}</h1>

      <form onSubmit={handleSubmit} className="space-y-4">
        <Input
          label={t('profile.name')}
          value={name}
          onChange={(event) => setName(event.target.value)}
          maxLength={160}
          error={update.error?.fieldError('name')}
        />
        <Input
          label={t('profile.phone')}
          value={phone}
          onChange={(event) => setPhone(event.target.value)}
          type="tel"
          maxLength={32}
          placeholder="+998 90 123 45 67"
          error={update.error?.fieldError('phone')}
        />
        <Select
          label={t('profile.locale')}
          value={selectedLocale}
          onChange={(event) => setSelectedLocale(event.target.value as TranslatedLocale)}
        >
          {LOCALE_OPTIONS.map((option) => (
            <option key={option.value} value={option.value}>
              {option.label}
            </option>
          ))}
        </Select>

        <Button type="submit" disabled={update.isPending} className="w-full">
          {t('profile.save')}
        </Button>

        {update.isSuccess && <p className="text-center text-sm text-green-700">{t('profile.saved')}</p>}
      </form>
    </div>
  )
}
