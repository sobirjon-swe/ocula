import { Boxes, LogOut, Package } from 'lucide-react'
import { NavLink, Outlet } from 'react-router-dom'

import { Select } from '@/components/ui/input'
import type { Locale } from '@/lib/api/types'
import { LOCALE_OPTIONS, useT } from '@/lib/i18n'
import { useSession } from '@/lib/session'
import { cn } from '@/lib/utils'

import { useLogout } from '@/features/auth/api'

const NAV = [
  { to: '/products', icon: Package, label: 'nav.products', permission: 'catalog.product.view_any' },
  { to: '/stock', icon: Boxes, label: 'nav.stock', permission: 'warehouse.stock.view' },
] as const

export function AppLayout() {
  const t = useT()
  const user = useSession((state) => state.user)
  const permissions = useSession((state) => state.permissions)
  const locale = useSession((state) => state.locale)
  const setLocale = useSession((state) => state.setLocale)
  const logout = useLogout()

  // Ruxsati yo'q bo'lim menyuda ko'rinmaydi. Bu qulaylik — haqiqiy
  // to'siq serverdagi Policy (`ResourcePolicy`).
  const visible = NAV.filter((item) => permissions.includes(item.permission))

  return (
    <div className="flex min-h-full">
      <aside className="hidden w-60 shrink-0 flex-col border-r border-neutral-200 bg-white lg:flex">
        <div className="flex h-14 items-center px-5 text-lg font-semibold tracking-tight">
          {t('app.name')}
        </div>

        <nav className="flex-1 space-y-1 px-3 py-2">
          {visible.map(({ to, icon: Icon, label }) => (
            <NavLink
              key={to}
              to={to}
              className={({ isActive }) =>
                cn(
                  'flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors',
                  isActive
                    ? 'bg-brand-50 text-brand-700'
                    : 'text-neutral-600 hover:bg-neutral-100 hover:text-neutral-900',
                )
              }
            >
              <Icon className="h-4 w-4" aria-hidden />
              {t(label)}
            </NavLink>
          ))}
        </nav>
      </aside>

      <div className="flex min-w-0 flex-1 flex-col">
        <header className="flex h-14 items-center justify-end gap-4 border-b border-neutral-200 bg-white px-6">
          <div className="mr-auto flex items-center gap-2 lg:hidden">
            <span className="font-semibold">{t('app.name')}</span>
          </div>

          {user !== null && (
            <div className="text-right text-sm leading-tight">
              <div className="font-medium text-neutral-900">{user.name}</div>
              <div className="text-neutral-500">{user.branch?.name ?? user.phone}</div>
            </div>
          )}

          <Select
            aria-label="Til"
            value={locale === 'uz-cyrl' ? 'uz-latn' : locale}
            onChange={(event) => setLocale(event.target.value as Locale)}
            className="w-36"
          >
            {LOCALE_OPTIONS.map((option) => (
              <option key={option.value} value={option.value}>
                {option.label}
              </option>
            ))}
          </Select>

          <button
            type="button"
            onClick={() => logout.mutate()}
            title={t('nav.logout')}
            className="rounded-lg p-2 text-neutral-500 transition-colors hover:bg-neutral-100 hover:text-neutral-900"
          >
            <LogOut className="h-4 w-4" aria-hidden />
            <span className="sr-only">{t('nav.logout')}</span>
          </button>
        </header>

        <main className="flex-1 p-6">
          <Outlet />
        </main>
      </div>
    </div>
  )
}
