import { useState } from 'react'
import { LogOut, Menu, X } from 'lucide-react'
import { NavLink, Outlet } from 'react-router-dom'
import { useLogout } from '@/features/auth/api'
import { useSession } from '@/lib/session'
import type { Locale } from '@/lib/api/types'
import { resources } from '@/features/workspace/resources'
import { useLocalePreference } from '@/lib/i18n/preference'
import { ErrorNotice } from '@/features/workspace/shared'
import { useWords } from '@/features/workspace/copy'
export function AppLayout() {
    const w = useWords(),
        user = useSession((s) => s.user),
        permissions = useSession((s) => s.permissions),
        locale = useSession((s) => s.locale),
        preference = useLocalePreference(),
        logout = useLogout(),
        [menu, setMenu] = useState(false)
    const nav = [
        {
            to: '/reports',
            label: w(
                'Bosh ekran va hisobotlar',
                'Обзор и отчёты',
                'Overview & reports',
            ),
            permission: 'analytics.dashboard.view',
        },
        {
            to: '/pos',
            label: w('Savdo', 'Продажа', 'Point of sale'),
            permission: 'sales.order.create',
        },
        ...resources.map((r) => ({
            to: '/work/' + r.path.replaceAll('/', '~'),
            label: w(...r.title),
            permission: permissions.includes(r.permission)
                ? r.permission
                : permissions.includes(r.alternatePermission ?? '')
                  ? (r.alternatePermission ?? r.permission)
                  : (r.createPermission ?? r.permission),
        })),
        {
            to: '/stock',
            label: w('Ombor qoldig‘i', 'Остатки склада', 'Stock balances'),
            permission: 'warehouse.stock.view',
        },
        {
            to: '/delivery',
            label: w('Yetkazish', 'Доставка', 'Delivery'),
            permission: 'delivery.trip.view_any',
        },
        {
            to: '/settings',
            label: w('Sozlamalar', 'Настройки', 'Settings'),
            permission: 'core.settings.view',
        },
    ].filter((r) => permissions.includes(r.permission))
    return (
        <div className="min-h-screen lg:flex">
            <aside
                className={
                    (menu ? 'fixed inset-0 z-40 flex' : 'hidden') +
                    ' w-full shrink-0 flex-col border-r border-neutral-200 bg-white lg:sticky lg:top-0 lg:flex lg:h-screen lg:w-60 print:hidden'
                }
            >
                <div className="flex h-16 shrink-0 items-center justify-between px-5 text-xl font-semibold tracking-tight">
                    OPTIKA
                    <button
                        className="rounded p-2 lg:hidden"
                        onClick={() => setMenu(false)}
                        aria-label={w(
                            'Menyuni yopish',
                            'Закрыть меню',
                            'Close menu',
                        )}
                    >
                        <X size={20} />
                    </button>
                </div>
                <nav
                    className="min-h-0 flex-1 space-y-1 overflow-y-auto px-3 pb-5"
                    aria-label={w(
                        'Asosiy menyu',
                        'Основная навигация',
                        'Main navigation',
                    )}
                >
                    {nav.map((item) => (
                        <NavLink
                            key={item.to}
                            to={item.to}
                            onClick={() => setMenu(false)}
                            className={({ isActive }) =>
                                'block rounded-lg px-3 py-2 text-sm font-medium ' +
                                (isActive
                                    ? 'bg-brand-50 text-brand-700'
                                    : 'text-neutral-600 hover:bg-neutral-100 hover:text-neutral-900')
                            }
                        >
                            {item.label}
                        </NavLink>
                    ))}
                </nav>
            </aside>
            <div className="min-w-0 flex-1">
                <header className="flex min-h-16 flex-wrap items-center gap-3 border-b border-neutral-200 bg-white px-4 py-3 sm:px-6 print:hidden">
                    <button
                        className="rounded p-2 lg:hidden"
                        onClick={() => setMenu(true)}
                        aria-label={w(
                            'Menyuni ochish',
                            'Открыть меню',
                            'Open menu',
                        )}
                    >
                        <Menu size={20} />
                    </button>
                    <div className="mr-auto text-sm">
                        <div className="font-medium">{user?.name}</div>
                        <div className="text-neutral-500">
                            {user?.branch?.name ?? user?.phone}
                        </div>
                    </div>
                    <select
                        aria-label={w('Til', 'Язык', 'Language')}
                        className="max-w-36 rounded-lg border border-neutral-300 bg-white px-2 py-2 text-sm"
                        value={locale}
                        onChange={(e) =>
                            preference.change(e.target.value as Locale)
                        }
                    >
                        <option value="uz-latn">O‘zbekcha</option>
                        <option value="uz-cyrl">Ўзбекча</option>
                        <option value="ru">Русский</option>
                        <option value="en">English</option>
                    </select>
                    <button
                        className="rounded-lg p-2 text-neutral-600 hover:bg-neutral-100"
                        onClick={() => logout.mutate()}
                        aria-label={w('Chiqish', 'Выйти', 'Sign out')}
                    >
                        <LogOut size={18} />
                    </button>
                </header>
                <main className="p-4 sm:p-6">
                    <ErrorNotice error={preference.error} />
                    <Outlet />
                </main>
            </div>
        </div>
    )
}
