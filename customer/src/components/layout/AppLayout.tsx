import { Glasses, Home, Package, User } from 'lucide-react'
import { NavLink, Outlet } from 'react-router-dom'

import { useT } from '@/lib/i18n'
import { cn } from '@/lib/utils'

const NAV = [
  { to: '/', icon: Home, label: 'nav.home', end: true },
  { to: '/orders', icon: Package, label: 'nav.orders', end: false },
  { to: '/prescriptions', icon: Glasses, label: 'nav.prescriptions', end: false },
  { to: '/profile', icon: User, label: 'nav.profile', end: false },
] as const

/**
 * Mobil-birinchi joylashuv — Mini App telefon ekranida ochiladi,
 * admin/dagi ish stoli sidebar'i bu yerga to'g'ri kelmaydi (§10:
 * "bir ekranda"). Pastki tab navigatsiya — Telegram/mobil odatiy naqshi.
 */
export function AppLayout() {
  const t = useT()

  return (
    <div className="flex min-h-full flex-col">
      <main className="flex-1 overflow-y-auto">
        <Outlet />
      </main>

      <nav className="fixed inset-x-0 bottom-0 flex border-t border-neutral-200 bg-white pb-[env(safe-area-inset-bottom)]">
        {NAV.map(({ to, icon: Icon, label, end }) => (
          <NavLink
            key={to}
            to={to}
            end={end}
            className={({ isActive }) =>
              cn(
                'flex flex-1 flex-col items-center gap-1 py-2.5 text-xs font-medium transition-colors',
                isActive ? 'text-brand-600' : 'text-neutral-400',
              )
            }
          >
            <Icon className="h-5 w-5" aria-hidden />
            {t(label)}
          </NavLink>
        ))}
      </nav>
    </div>
  )
}
