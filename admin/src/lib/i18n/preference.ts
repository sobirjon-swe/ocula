import { useCallback, useEffect, useState } from 'react'
import { api } from '@/lib/api/client'
import { useSession } from '@/lib/session'
import type { Locale, User } from '@/lib/api/types'
export function useLocalePreference() {
    const userId = useSession((s) => s.user?.id),
        setLocale = useSession((s) => s.setLocale),
        [error, setError] = useState<unknown>(null)
    const sync = useCallback(async () => {
        if (!userId || !navigator.onLine) return
        const key = 'optika.locale.pending.' + userId,
            locale = localStorage.getItem(key) as Locale | null
        if (!locale) return
        try {
            const response = await api.put<{ data: User }>('/auth/locale', {
                body: { locale },
            })
            if (useSession.getState().user?.id !== userId) return
            useSession
                .getState()
                .setUser(
                    {
                        ...useSession.getState().user,
                        ...response.data,
                        branch:
                            response.data.branch ??
                            useSession.getState().user?.branch,
                    },
                    useSession.getState().permissions,
                )
            if (localStorage.getItem(key) === locale)
                localStorage.removeItem(key)
            setError(null)
        } catch (error) {
            setError(error)
        }
    }, [userId])
    useEffect(() => {
        void sync()
        window.addEventListener('online', sync)
        return () => window.removeEventListener('online', sync)
    }, [sync])
    return {
        error,
        change: (locale: Locale) => {
            setLocale(locale)
            if (userId)
                localStorage.setItem('optika.locale.pending.' + userId, locale)
            void sync()
        },
    }
}
