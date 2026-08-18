import { Share2 } from 'lucide-react'

import { Button } from '@/components/ui/button'
import { Badge, ErrorBlock, LoadingBlock } from '@/components/ui/feedback'
import { useT } from '@/lib/i18n'
import type { MessageKey } from '@/lib/i18n'
import { formatDate } from '@/lib/utils'
import type { Prescription } from '@/lib/api/types'

import { usePrescriptions } from './api'

/**
 * "Retsept — saqlab olsa bo'ladigan chiroyli karta" (PROJECT.md §10) —
 * boshqa optikada ko'rsatilsa bepul reklama. Shu sabab bu yerda alohida
 * karta dizayni, jadval emas.
 */
export function PrescriptionsPage() {
  const t = useT()
  const prescriptions = usePrescriptions()

  return (
    <div className="space-y-4 p-4 pb-24">
      <h1 className="text-xl font-semibold text-neutral-950">{t('prescriptions.title')}</h1>

      {prescriptions.isPending && <LoadingBlock label={t('app.loading')} />}
      {prescriptions.isError && (
        <ErrorBlock error={prescriptions.error} onRetry={() => prescriptions.refetch()} />
      )}
      {prescriptions.data !== undefined && prescriptions.data.data.length === 0 && (
        <p className="text-sm text-neutral-500">{t('prescriptions.empty')}</p>
      )}

      <div className="space-y-4">
        {prescriptions.data?.data.map((prescription) => (
          <PrescriptionCard key={prescription.id} prescription={prescription} />
        ))}
      </div>
    </div>
  )
}

function PrescriptionCard({ prescription }: { prescription: Prescription }) {
  const t = useT()

  function share() {
    const text = [
      `OPTIKA — ${t('prescriptions.title')}`,
      `${t('prescriptions.od')}: SPH ${prescription.od.sph ?? '—'} CYL ${prescription.od.cyl ?? '—'} AXIS ${prescription.od.axis ?? '—'}`,
      `${t('prescriptions.os')}: SPH ${prescription.os.sph ?? '—'} CYL ${prescription.os.cyl ?? '—'} AXIS ${prescription.os.axis ?? '—'}`,
      prescription.pd !== null ? `PD: ${prescription.pd}` : null,
    ]
      .filter((line) => line !== null)
      .join('\n')

    if (navigator.share !== undefined) {
      navigator.share({ text }).catch(() => {})
    }
  }

  return (
    <div className="overflow-hidden rounded-2xl bg-gradient-to-br from-brand-600 to-brand-700 text-white shadow-lg">
      <div className="flex items-start justify-between p-5 pb-3">
        <div>
          <div className="text-xs font-medium tracking-wide text-brand-100 uppercase">OPTIKA</div>
          <div className="text-sm text-brand-100">{formatDate(prescription.created_at)}</div>
        </div>
        {prescription.is_expired && <Badge tone="red">{t('prescriptions.expired')}</Badge>}
      </div>

      <div className="grid grid-cols-2 gap-3 px-5 pb-4">
        <EyeBlock label={t('prescriptions.od')} eye={prescription.od} t={t} />
        <EyeBlock label={t('prescriptions.os')} eye={prescription.os} t={t} />
      </div>

      <div className="flex flex-wrap gap-x-6 gap-y-1 px-5 pb-4 text-sm text-brand-50">
        {prescription.pd !== null && <span>PD: {prescription.pd}</span>}
        {prescription.pd_near !== null && (
          <span>
            {t('prescriptions.pdNear')}: {prescription.pd_near}
          </span>
        )}
        {prescription.valid_until !== null && (
          <span>
            {t('prescriptions.validUntil')}: {formatDate(prescription.valid_until)}
          </span>
        )}
      </div>

      <div className="flex items-center justify-between rounded-b-2xl bg-black/10 px-5 py-3 text-sm">
        <div>
          <div className="font-medium">{prescription.doctor}</div>
          <div className="text-brand-100">{prescription.branch}</div>
        </div>
        {navigator.share !== undefined && (
          <Button variant="ghost" size="md" onClick={share} className="text-white hover:bg-white/10">
            <Share2 className="h-4 w-4" aria-hidden />
            {t('prescriptions.save')}
          </Button>
        )}
      </div>
    </div>
  )
}

function EyeBlock({
  label,
  eye,
  t,
}: {
  label: string
  eye: Prescription['od']
  t: (key: MessageKey) => string
}) {
  return (
    <div className="rounded-xl bg-white/10 p-3">
      <div className="mb-1.5 text-xs font-semibold tracking-wide text-brand-100 uppercase">{label}</div>
      <dl className="space-y-0.5 text-sm">
        <div className="flex justify-between">
          <dt className="text-brand-100">{t('prescriptions.sph')}</dt>
          <dd>{eye.sph ?? '—'}</dd>
        </div>
        <div className="flex justify-between">
          <dt className="text-brand-100">{t('prescriptions.cyl')}</dt>
          <dd>{eye.cyl ?? '—'}</dd>
        </div>
        <div className="flex justify-between">
          <dt className="text-brand-100">{t('prescriptions.axis')}</dt>
          <dd>{eye.axis ?? '—'}</dd>
        </div>
        <div className="flex justify-between">
          <dt className="text-brand-100">{t('prescriptions.add')}</dt>
          <dd>{eye.add ?? '—'}</dd>
        </div>
      </dl>
    </div>
  )
}
