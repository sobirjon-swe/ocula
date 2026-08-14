import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'

import { api } from '@/lib/api/client'
import type { Envelope, LoginResult, User } from '@/lib/api/types'
import { useSession } from '@/lib/session'

/** Token nomi — xodim "qaysi qurilmadan kirganman" ro'yxatida shu ko'rinadi. */
function deviceName(): string {
  return `Admin panel · ${navigator.platform || 'web'}`
}

export function useLogin() {
  const signIn = useSession((state) => state.signIn)

  return useMutation({
    mutationFn: (input: { phone: string; password: string }) =>
      api.post<Envelope<LoginResult>>('/auth/login', {
        body: { ...input, device_name: deviceName() },
      }),
    onSuccess: (response) => signIn(response.data),
  })
}

/**
 * Sahifa yangilanganda saqlangan token hali amal qiladimi — shu so'rov
 * hal qiladi. Ruxsatlar ham shu yerdan yangilanadi: direktor rolni
 * o'zgartirsa, xodim qayta kirmasdan yangi ro'yxatni oladi.
 */
export function useMe() {
  const token = useSession((state) => state.token)
  const setUser = useSession((state) => state.setUser)

  return useQuery({
    queryKey: ['auth', 'me'],
    enabled: token !== null,
    retry: false,
    staleTime: 60_000,
    queryFn: async () => {
      const response = await api.get<Envelope<User> & { meta?: { permissions?: string[] } }>(
        '/auth/me',
      )

      setUser(response.data, response.meta?.permissions ?? [])

      return response.data
    },
  })
}

export function useLogout() {
  const signOut = useSession((state) => state.signOut)
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: () => api.post<null>('/auth/logout'),
    // Token allaqachon yaroqsiz bo'lsa ham lokal sessiya tozalanadi —
    // xodim "chiqa olmayapman" holatida qolib ketmasin.
    onSettled: () => {
      signOut()
      queryClient.clear()
    },
  })
}
