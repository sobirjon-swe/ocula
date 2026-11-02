import { useState } from 'react'
import { api } from '@/lib/api/client'
import { useWords } from './copy'
import { ErrorNotice } from './shared'
export function ReceiptButton({ orderId }: { orderId: number }) {
    const w = useWords(),
        [error, setError] = useState<unknown>(null),
        [busy, setBusy] = useState(false)
    async function print() {
        const popup = window.open('', '_blank')
        if (!popup) {
            setError(
                new Error(
                    w(
                        'Chekni ochish uchun yangi oynaga ruxsat bering.',
                        'Разрешите всплывающие окна для открытия чека.',
                        'Allow popups to open the receipt.',
                    ),
                ),
            )
            return
        }
        setBusy(true)
        try {
            const response = await api.get<{
                data: { html: string; is_fiscal: false }
            }>('/orders/' + orderId + '/receipt')
            popup.document.open()
            popup.document.write(response.data.html)
            popup.document.close()
            popup.focus()
            popup.print()
            setError(null)
        } catch (error) {
            popup.close()
            setError(error)
        } finally {
            setBusy(false)
        }
    }
    return (
        <div>
            <button
                className="action-secondary"
                disabled={busy}
                onClick={() => void print()}
            >
                {w('Chekni chop etish', 'Печать чека', 'Print receipt')}
            </button>
            <ErrorNotice error={error} />
        </div>
    )
}
