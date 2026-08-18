import { useEffect } from 'react'
import { Navigate, Route, Routes, useNavigate } from 'react-router-dom'

import { AppLayout } from '@/components/layout/AppLayout'
import { AuthGate } from '@/features/auth/AuthGate'
import { DebtPage } from '@/features/debt/DebtPage'
import { DeliveryConfirmPage } from '@/features/delivery/DeliveryConfirmPage'
import { HomePage } from '@/features/home/HomePage'
import { OrderDetailPage } from '@/features/orders/OrderDetailPage'
import { OrdersPage } from '@/features/orders/OrdersPage'
import { PrescriptionsPage } from '@/features/prescriptions/PrescriptionsPage'
import { ProfilePage } from '@/features/profile/ProfilePage'
import { getStartParam } from '@/lib/telegram'

/**
 * Bot havolasi `?startapp=stop_123` bilan ochilsa, ilova ochilishi bilan
 * yetkazishni tasdiqlash ekraniga o'tkazadi (BOSQICH-9.md §6). Faqat
 * ilk yuklanishda — foydalanuvchi keyin ilova ichida erkin yuradi.
 */
function useStartParamRedirect(): void {
  const navigate = useNavigate()

  useEffect(() => {
    const startParam = getStartParam()
    const match = startParam !== null ? /^stop_(\d+)$/.exec(startParam) : null

    if (match !== null) {
      navigate(`/delivery/${match[1]}`, { replace: true })
    }
    // Faqat ilk render'da tekshiriladi — `navigate` funksiyasi barqaror.
  }, [])
}

export function AppRoutes() {
  useStartParamRedirect()

  return (
    <AuthGate>
      <Routes>
        {/* Yetkazishni tasdiqlash bosh navigatsiyadan tashqarida — bot
            havolasidan to'g'ridan-to'g'ri ochiladi, pastki tab shart emas
            (BOSQICH-9.md §6). */}
        <Route path="/delivery/:stopId" element={<DeliveryConfirmPage />} />

        <Route element={<AppLayout />}>
          <Route index element={<HomePage />} />
          <Route path="/orders" element={<OrdersPage />} />
          <Route path="/orders/:id" element={<OrderDetailPage />} />
          <Route path="/prescriptions" element={<PrescriptionsPage />} />
          <Route path="/debt" element={<DebtPage />} />
          <Route path="/profile" element={<ProfilePage />} />
        </Route>

        <Route path="*" element={<Navigate to="/" replace />} />
      </Routes>
    </AuthGate>
  )
}
