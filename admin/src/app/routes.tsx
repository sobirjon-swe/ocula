import { Navigate, Route, Routes } from 'react-router-dom'
import { AppLayout } from '@/components/layout/AppLayout'
import { LoadingBlock } from '@/components/ui/feedback'
import { useMe } from '@/features/auth/api'
import { LoginPage } from '@/features/auth/LoginPage'
import { DeliveryPage } from '@/features/delivery/DeliveryPage'
import { StockPage } from '@/features/stock/StockPage'
import { WorkshopPage } from '@/features/workspace/WorkshopPage'
import { PosPage } from '@/features/workspace/PosPage'
import { ResourcePage } from '@/features/workspace/ResourcePage'
import { ReportsPage, SettingsPage } from '@/features/workspace/ReportsPage'
import { resources } from '@/features/workspace/resources'
import { useT } from '@/lib/i18n'
import { ApiError } from '@/lib/api/client'
import { useSession } from '@/lib/session'
function RequireAuth() {
    const t = useT(),
        token = useSession((s) => s.token),
        user = useSession((s) => s.user),
        me = useMe()
    if (token === null) return <Navigate to="/login" replace />
    if (
        user &&
        (!navigator.onLine ||
            (me.error instanceof ApiError && me.error.status === 0))
    )
        return <AppLayout />
    if (me.isPending) return <LoadingBlock label={t('app.loading')} />
    if (me.isError) return <Navigate to="/login" replace />
    return <AppLayout />
}
function Permit({
    permission,
    children,
}: {
    permission: string
    children: React.ReactNode
}) {
    const p = useSession((s) => s.permissions)
    return p.includes(permission) ? children : <Navigate to="/" replace />
}
function Home() {
    const p = useSession((s) => s.permissions)
    return (
        <Navigate
            to={
                p.includes('sales.order.create')
                    ? '/pos'
                    : p.includes('analytics.dashboard.view')
                      ? '/reports'
                      : '/work/' +
                        (
                            resources.find((r) => p.includes(r.permission))
                                ?.path ?? 'bonus-entries'
                        ).replaceAll('/', '~')
            }
            replace
        />
    )
}
export function AppRoutes() {
    return (
        <Routes>
            <Route path="/login" element={<LoginPage />} />
            <Route element={<RequireAuth />}>
                <Route index element={<Home />} />
                <Route
                    path="/delivery"
                    element={
                        <Permit permission="delivery.trip.view_any">
                            <DeliveryPage />
                        </Permit>
                    }
                />
                <Route
                    path="/pos"
                    element={
                        <Permit permission="sales.order.create">
                            <PosPage />
                        </Permit>
                    }
                />
                <Route path="/reports" element={<ReportsPage />} />
                <Route
                    path="/settings"
                    element={
                        <Permit permission="core.settings.view">
                            <SettingsPage />
                        </Permit>
                    }
                />
                <Route
                    path="/stock"
                    element={
                        <Permit permission="warehouse.stock.view">
                            <StockPage />
                        </Permit>
                    }
                />
                <Route
                    path="/products"
                    element={<Navigate to="/work/products" replace />}
                />
                {resources.map((resource) => (
                    <Route
                        key={resource.path}
                        path={'/work/' + resource.path.replaceAll('/', '~')}
                        element={
                            resource.path === 'work-orders' ? (
                                <Permit permission="workshop.work_order.view_any">
                                    <WorkshopPage />
                                </Permit>
                            ) : (
                                <ResourcePage
                                    key={resource.path}
                                    resource={resource}
                                />
                            )
                        }
                    />
                ))}
            </Route>
            <Route path="*" element={<Navigate to="/" replace />} />
        </Routes>
    )
}
