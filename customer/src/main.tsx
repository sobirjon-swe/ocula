import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import { BrowserRouter } from 'react-router-dom'

import { AppRoutes } from '@/app/routes'
import { ApiError } from '@/lib/api/client'

import './index.css'
// Sessiya moduli API klientini sozlaydi — birinchi so'rovdan oldin
// yuklanishi shart.
import { useSession } from '@/lib/session'

const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      staleTime: 30_000,
      refetchOnWindowFocus: false,
      // 401/403/422 ni qayta urinib ko'rishning ma'nosi yo'q — javob
      // o'zgarmaydi. Faqat tarmoq va server xatolari qaytariladi.
      retry: (failureCount, error) => {
        if (error instanceof ApiError && error.status >= 400 && error.status < 500) {
          return false
        }

        return failureCount < 2
      },
    },
  },
})

// Remove all private responses whenever the authenticated identity changes.
useSession.subscribe((next, previous) => {
  if (next.token !== previous.token) {
    void queryClient.cancelQueries()
    queryClient.clear()
  }
  document.documentElement.lang = next.locale
})
document.documentElement.lang = useSession.getState().locale
if (import.meta.env.PROD && 'serviceWorker' in navigator) {
  void navigator.serviceWorker.register('/sw.js').catch(() => {})
}
const container = document.getElementById('root')

if (container === null) {
  throw new Error('#root topilmadi')
}

createRoot(container).render(
  <StrictMode>
    <QueryClientProvider client={queryClient}>
      <BrowserRouter>
        <AppRoutes />
      </BrowserRouter>
    </QueryClientProvider>
  </StrictMode>,
)
