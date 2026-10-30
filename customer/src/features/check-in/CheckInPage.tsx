import { useRef, useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api, idempotencyKey } from '@/lib/api/client'
import type { Envelope } from '@/lib/api/types'
import { useT } from '@/lib/i18n'
import { Button } from '@/components/ui/button'
import { Card, ErrorBlock, LoadingBlock } from '@/components/ui/feedback'

type Visit = { id: number; branch_id: number; branch: string; queue_number: number; status: 'waiting' | 'in_progress' }
type Branch = { id: number; name: string }

export function CheckInPage() {
  const t = useT()
  const [params] = useSearchParams()
  const [branch, setBranch] = useState(params.get('branch') ?? '')
  const key = useRef(idempotencyKey())
  const client = useQueryClient()
  const branches = useQuery({ queryKey: ['customer', 'check-in-branches'], queryFn: () => api.get<Envelope<Branch[]>>('/public/branches').then(r => r.data) })
  const queue = useQuery({ queryKey: ['customer', 'visits'], queryFn: () => api.get<Envelope<Visit[]>>('/customer/visits').then(r => r.data), refetchInterval: 15000 })
  const create = useMutation({
    mutationFn: () => api.post<Envelope<Visit>>('/customer/visits', { body: { branch_id: Number(branch) }, idempotencyKey: key.current }),
    onSuccess: (response) => {
      client.setQueryData<Visit[]>(['customer', 'visits'], (previous = []) => [...previous.filter(visit => visit.id !== response.data.id), response.data])
      return client.invalidateQueries({ queryKey: ['customer', 'visits'] })
    },
  })
  const visits = queue.data ?? []
  return <div className="space-y-4 p-4 pb-24">
    <h1 className="text-xl font-semibold">{t('checkin.title')}</h1>
    <p className="text-sm text-neutral-600">{t('checkin.description')}</p>
    {queue.isPending && <LoadingBlock label={t('app.loading')} />}
    {queue.isError && <ErrorBlock error={queue.error} onRetry={() => queue.refetch()} />}
    {visits.map(visit => <Card key={visit.id}><p>{visit.branch}</p><p className="mt-2 text-sm">{t('checkin.number')}</p><p className="my-2 text-4xl font-semibold tabular-nums">{visit.queue_number}</p><p role="status">{t(visit.status === 'in_progress' ? 'checkin.inProgress' : 'checkin.waiting')}</p></Card>)}
    {branches.isPending && <LoadingBlock label={t('app.loading')} />}
    {branches.isError && <ErrorBlock error={branches.error} onRetry={() => branches.refetch()} />}
    {branches.data && <form className="space-y-4" onSubmit={event => { event.preventDefault(); if (!create.isPending) create.mutate() }}>
      <label className="block text-sm">{t('prescriptions.branch')}<select required value={branch} onChange={event => { setBranch(event.target.value); key.current = idempotencyKey(); create.reset() }} className="mt-2 min-h-12 w-full rounded-lg border border-neutral-300 bg-white p-3 text-base"><option value="">—</option>{branches.data.map(item => <option key={item.id} value={item.id}>{item.name}</option>)}</select></label>
      {create.isError && <ErrorBlock error={create.error} />}
      <Button type="submit" disabled={create.isPending || queue.isPending || !branch || visits.some(visit => visit.branch_id === Number(branch))}>{t('checkin.join')}</Button>
    </form>}
  </div>
}
