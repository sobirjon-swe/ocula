import { Navigate, Route, Routes } from 'react-router-dom'

import { AppLayout } from '@/components/layout/AppLayout'
import { LoadingBlock } from '@/components/ui/feedback'
import { useMe } from '@/features/auth/api'
import { LoginPage } from '@/features/auth/LoginPage'
import { ProductsPage } from '@/features/products/ProductsPage'
import { StockPage } from '@/features/stock/StockPage'
import { useT } from '@/lib/i18n'
import { useSession } from '@/lib/session'

/**
 * Himoyalangan qism. Saqlangan token bilan sahifa yangilanganda `/auth/me`
 * uni tekshiradi va ruxsatlar ro'yxatini yangilaydi; token yaroqsiz bo'lsa
 * klient sessiyani tozalaydi (`onUnauthenticated`) va kirish sahifasiga
 * qaytariladi.
 */
function RequireAuth() {
  const t = useT()
  const token = useSession((state) => state.token)
  const me = useMe()

  if (token === null) {
    return <Navigate to="/login" replace />
  }

  if (me.isPending) {
    return <LoadingBlock label={t('app.loading')} />
  }

  if (me.isError) {
    return <Navigate to="/login" replace />
  }

  return <AppLayout />
}

export function AppRoutes() {
  return (
    <Routes>
      <Route path="/login" element={<LoginPage />} />

      <Route element={<RequireAuth />}>
        <Route index element={<Navigate to="/products" replace />} />
        <Route path="/products" element={<ProductsPage />} />
        <Route path="/stock" element={<StockPage />} />
      </Route>

      <Route path="*" element={<Navigate to="/" replace />} />
    </Routes>
  )
}
