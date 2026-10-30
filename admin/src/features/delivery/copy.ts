import { useTranslation } from 'react-i18next'
import { useSession } from '@/lib/session'
import { i18n } from '@/lib/i18n/runtime'
import { cyrillic } from '@/lib/i18n/transliterate'
import type { MessageKey } from '@/lib/i18n/messages'
type DeliveryKey =
    | 'title'
    | 'route'
    | 'refresh'
    | 'empty'
    | 'pending'
    | 'online'
    | 'offline'
    | 'start'
    | 'finish'
    | 'delivered'
    | 'fail'
    | 'reason'
    | 'save'
    | 'cancel'
    | 'photo'
    | 'navigator'
    | 'phone'
    | 'collect'
    | 'balance'
    | 'goods'
    | 'retry'
    | 'discard'
    | 'conflict'
    | 'location'
    | 'planned'
    | 'in_progress'
    | 'finished'
    | 'cancelled'
    | 'stopPending'
    | 'stopDelivered'
    | 'stopFailed'
    | 'newTrip'
    | 'driver'
    | 'date'
    | 'add'
    | 'newStop'
    | 'type'
    | 'customer'
    | 'branch'
    | 'order'
    | 'transfer'
    | 'address'
    | 'lat'
    | 'lng'
    | 'map'
    | 'collection'
    | 'amount'
    | 'choose'
    | 'gps'
    | 'saved'
    | 'status'
export function useDeliveryCopy() {
    useTranslation()
    const locale = useSession((s) => s.locale)
    return (key: DeliveryKey): string => {
        const value = String(
            i18n.t(('delivery.' + key) as MessageKey, {
                lng: locale === 'uz-cyrl' ? 'uz-latn' : locale,
            }),
        )
        return locale === 'uz-cyrl' ? cyrillic(value) : value
    }
}
