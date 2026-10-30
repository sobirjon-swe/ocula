import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Star } from 'lucide-react'
import { api } from '@/lib/api/client'
import type { Envelope } from '@/lib/api/types'
import { useT } from '@/lib/i18n'
import { Button } from '@/components/ui/button'
import { Card, ErrorBlock, LoadingBlock } from '@/components/ui/feedback'
type Rating={stars:number;comment:string|null}
export function RatingForm({orderId}:{orderId:number}) {
 const t=useT();const client=useQueryClient();const [stars,setStars]=useState(5);const [comment,setComment]=useState('')
 const key=['customer','rating',orderId];const path='/customer/orders/'+orderId+'/rating'
 const current=useQuery({queryKey:key,queryFn:()=>api.get<Envelope<Rating|null>>(path).then(r=>r.data)})
 const save=useMutation({mutationFn:()=>api.post<Envelope<Rating>>(path,{body:{stars,comment:comment.trim()||null}}),onSuccess:()=>client.invalidateQueries({queryKey:key})})
 return <Card><h2 className="mb-3 font-semibold">{t('rating.title')}</h2>
 {current.isPending ? <LoadingBlock label={t('app.loading')}/> : current.isError ? <ErrorBlock error={current.error} onRetry={()=>current.refetch()}/> : current.data ? <div role="status"><p>{t('rating.saved')} ({current.data.stars}/5)</p><p className="mt-2 whitespace-pre-wrap break-words">{current.data.comment}</p></div> : <form className="space-y-3" onSubmit={e=>{e.preventDefault();if(!save.isPending)save.mutate()}}>
 <fieldset><legend className="text-sm">{t('rating.stars')}</legend><div className="flex gap-1">{[1,2,3,4,5].map(value=><label key={value} className="relative flex h-12 w-12 cursor-pointer items-center justify-center"><input className="peer sr-only" type="radio" name="stars" value={value} checked={stars===value} onChange={()=>setStars(value)} aria-label={value+'/5'}/><Star aria-hidden className={'h-8 w-8 peer-focus-visible:outline '+(value<=stars?'fill-amber-400 text-amber-700':'text-neutral-500')}/></label>)}</div></fieldset>
 <label className="block text-sm">{t('rating.comment')}<textarea value={comment} onChange={e=>setComment(e.target.value)} maxLength={1000} rows={3} className="mt-1 block w-full rounded-lg border border-neutral-300 p-3 text-base"/></label>
 {save.isError&&<ErrorBlock error={save.error}/>}<Button type="submit" disabled={save.isPending}>{t('rating.submit')}</Button></form>}</Card>
}
