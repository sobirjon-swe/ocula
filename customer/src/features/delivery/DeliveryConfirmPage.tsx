import { CheckCircle2, XCircle } from 'lucide-react'
import { useParams } from 'react-router-dom'

import { Button } from '@/components/ui/button'
import { Card, ErrorBlock } from '@/components/ui/feedback'
import { useT } from '@/lib/i18n'

import { useConfirmDelivery, useDisputeDelivery } from './api'

/**
 * Telegram botning "Buyurtmangiz yetkazildimi?" xabaridagi havoladan
 * ochiladi (`t.me/bot/app?startapp=stop_123` → `/delivery/123`,
 * BOSQICH-9.md §6). Ro'yxatdan emas, faqat shu havoladan kirilishi
 * kutiladi.
 */
export function DeliveryConfirmPage() {
  const t = useT()
  const { stopId } = useParams<{ stopId: string }>()
  const id = Number(stopId)

  const confirm = useConfirmDelivery(id)
  const dispute = useDisputeDelivery(id)

  if (!Number.isFinite(id)) {
    return (
      <div className="p-4">
        <ErrorBlock error={new Error(t('delivery.error'))} />
      </div>
    )
  }

  const result = confirm.data ?? dispute.data

  return (
    <div className="flex min-h-full flex-col items-center justify-center gap-6 p-6 text-center">
      <h1 className="text-lg font-semibold text-neutral-950">{t('delivery.title')}</h1>

      {result === undefined ? (
        <>
          <p className="text-neutral-600">{t('delivery.question')}</p>

          {(confirm.isError || dispute.isError) && (
            <ErrorBlock error={confirm.error ?? dispute.error} />
          )}

          <div className="flex w-full max-w-xs flex-col gap-3">
            <Button
              size="lg"
              onClick={() => confirm.mutate()}
              disabled={confirm.isPending || dispute.isPending}
              className="w-full"
            >
              <CheckCircle2 className="h-5 w-5" aria-hidden />
              {t('delivery.confirm')}
            </Button>
            <Button
              variant="secondary"
              size="lg"
              onClick={() => dispute.mutate()}
              disabled={confirm.isPending || dispute.isPending}
              className="w-full"
            >
              <XCircle className="h-5 w-5" aria-hidden />
              {t('delivery.dispute')}
            </Button>
          </div>
        </>
      ) : (
        <Card className="w-full max-w-xs">
          <p className="text-neutral-700">
            {result.data.confirmation_status === 'confirmed' ? t('delivery.confirmed') : t('delivery.disputed')}
          </p>
        </Card>
      )}
    </div>
  )
}
