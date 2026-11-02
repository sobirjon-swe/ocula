import { useCallback, useEffect, useMemo, useState } from 'react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { Camera, MapPin, Navigation, Phone, RefreshCw, Truck } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Input, Select } from '@/components/ui/input'
import { api, idempotencyKey } from '@/lib/api/client'
import type { Envelope, User } from '@/lib/api/types'
import { createOutbox } from '@/lib/offline/queue'
import type { PendingOperation } from '@/lib/offline/queue'
import { useSession } from '@/lib/session'
import { useDeliveryCopy } from './copy'
import { MapPicker } from './MapPicker'
import { money } from '@/features/workspace/money'

type Stop = { id:number; sequence:number; type:string; status:string; address:string|null; lat:string|null; lng:string|null; cash_to_collect:string; contact_name?:string|null; contact_phone?:string|null; order_id:number|null; transfer_id:number|null }
type Trip = { id:number; driver_id:number; date:string; status:'planned'|'in_progress'|'finished'|'cancelled'; stops:Stop[] }
type List<T> = {data:T[]}
const amount=money
const today=()=>new Intl.DateTimeFormat('en-CA',{timeZone:'Asia/Tashkent',year:'numeric',month:'2-digit',day:'2-digit'}).format(new Date())
const errorText=(e:unknown)=>e instanceof Error?e.message:String(e)

export function DeliveryPage() {
  const t=useDeliveryCopy()
  const user=useSession(s=>s.user)
  const permissions=useSession(s=>s.permissions)
  const queryClient=useQueryClient()
  const owner=user?.id ?? 0
  const dispatcher=permissions.includes('delivery.trip.create')
  const cacheKey=`optika.driver.trips.${owner}`
  const outbox=useMemo(()=>createOutbox(localStorage,owner),[owner])
  const [pending,setPending]=useState<PendingOperation[]>(()=>outbox.list())
  const [error,setError]=useState('')
  const [online,setOnline]=useState(navigator.onLine)
  const [busy,setBusy]=useState<number|null>(null)
  const [reasonStop,setReasonStop]=useState<number|null>(null)
  const [reason,setReason]=useState('')
  const [photo,setPhoto]=useState<Record<number,string>>({})
  const trips=useQuery({queryKey:['delivery-trips',owner],queryFn:async()=>{
    const result=await api.get<List<Trip>>('/trips',{params:{per_page:100,...(!dispatcher?{filter:{driver_id:owner}}:{})}})
    localStorage.setItem(cacheKey,JSON.stringify(result.data))
    return result.data
  },initialData:()=>{
    try { return JSON.parse(localStorage.getItem(cacheKey)??'null') as Trip[]|undefined } catch { return undefined }
  },refetchInterval:online?30_000:false})
  const balances=useQuery({queryKey:['driver-balance',owner],queryFn:async()=>{
    const result=await api.get<Envelope<{cash_amount:string;goods_quantity?:number}>>(`/driver-balances/${owner}`)
    localStorage.setItem(`optika.driver.balance.${owner}`,JSON.stringify(result))
    return result
  },initialData:()=>{
    try { return JSON.parse(localStorage.getItem(`optika.driver.balance.${owner}`)??'null') as Envelope<{cash_amount:string;goods_quantity?:number}>|undefined } catch { return undefined }
  },enabled:!dispatcher})
  const flush=useCallback(async()=>{
    await outbox.flush(()=>useSession.getState().user?.id,op=>api.post(op.path,{body:op.body,idempotencyKey:op.key}))
    setPending(outbox.list())
    if(navigator.onLine) { void queryClient.invalidateQueries({queryKey:['delivery-trips',owner]}); void queryClient.invalidateQueries({queryKey:['driver-balance',owner]}) }
  },[outbox,owner,queryClient])
  useEffect(()=>{
    const connect=()=>{setOnline(true);void flush()}
    const disconnect=()=>setOnline(false)
    window.addEventListener('online',connect);window.addEventListener('offline',disconnect)
    const timer=window.setInterval(()=>{if(navigator.onLine) void flush()},30_000)
    if('serviceWorker' in navigator && import.meta.env.PROD) void navigator.serviceWorker.register('/driver-sw.js').catch(()=>{})
    if(navigator.onLine) void flush()
    return ()=>{window.removeEventListener('online',connect);window.removeEventListener('offline',disconnect);window.clearInterval(timer)}
  },[flush])
  async function enqueue(path:string,body:Record<string,unknown>={}) {
    try { setError('');outbox.enqueue(path,body);setPending(outbox.list());if(navigator.onLine) await flush() } catch(e) {setError(errorText(e))}
  }
  async function deliver(stop:Stop) {
    setBusy(stop.id);setError('')
    try {
      const position=await new Promise<GeolocationPosition>((resolve,reject)=>navigator.geolocation.getCurrentPosition(resolve,reject,{enableHighAccuracy:true,timeout:15000,maximumAge:0}))
      await enqueue(`/trip-stops/${stop.id}/deliver`,{lat:String(position.coords.latitude),lng:String(position.coords.longitude),...(photo[stop.id]?{photo:photo[stop.id]}:{})})
    } catch {setError(t('location'))} finally {setBusy(null)}
  }
  async function readPhoto(stop:number,file:File|undefined) {
    if(!file) return
    try {
      const source=await createImageBitmap(file)
      const canvas=document.createElement('canvas');const scale=Math.min(1,1280/Math.max(source.width,source.height))
      canvas.width=Math.round(source.width*scale);canvas.height=Math.round(source.height*scale)
      canvas.getContext('2d')!.drawImage(source,0,0,canvas.width,canvas.height);source.close()
      const data=canvas.toDataURL('image/jpeg',0.75)
      if(data.length>2_800_000) throw new Error('Rasm juda katta. Kichikroq rasm tanlang.')
      setPhoto(current=>({...current,[stop]:data}))
    } catch(e) {setError(errorText(e))}
  }
  return <div className="mx-auto max-w-5xl space-y-6">
    <div className="flex flex-wrap items-center justify-between gap-3"><h1 className="text-2xl font-semibold">{t('title')}</h1><Button variant="secondary" onClick={()=>{void trips.refetch();void flush()}}><RefreshCw size={18}/>{t('refresh')}</Button></div>
    <div role="status" className="flex flex-wrap justify-between gap-3 bg-neutral-900 p-4 text-lg font-semibold text-white"><span>{online?t('online'):t('offline')}</span><span>{pending.length} {t('pending')}</span></div>
    {!dispatcher && <div className="sticky top-0 z-10 flex flex-wrap gap-6 bg-white p-4 text-xl font-semibold shadow-sm"><span>{t('balance')}: {amount(balances.data?.data.cash_amount??'0')}</span><span>{t('goods')}: {balances.data?.data.goods_quantity??'—'}</span></div>}
    {error && <p role="alert" className="rounded-lg bg-red-50 p-4 text-red-800">{error}</p>}
    {pending.filter(op=>op.error).map(op=><section key={op.key} className="space-y-3 rounded-xl border border-red-300 p-4"><p>{t('conflict')}</p><p role="alert">{op.error}</p><div className="flex flex-wrap gap-3"><Button onClick={()=>{outbox.retry(op.key);void flush()}}>{t('retry')}</Button><Button variant="secondary" onClick={()=>{outbox.discard(op.key);setPending(outbox.list())}}>{t('discard')}</Button></div></section>)}
    {dispatcher && <DispatchForms trips={trips.data??[]} onSaved={()=>{void trips.refetch()}}/>}
    {trips.isPending && <div aria-busy="true" className="h-64 animate-pulse rounded-xl bg-neutral-100"/>}
    {trips.error && !trips.data && <p role="alert">{errorText(trips.error)}</p>}
    {trips.data?.length===0 && <p className="py-16 text-center text-neutral-600">{t('empty')}</p>}
    {trips.data?.map(trip=><section key={trip.id} className="space-y-4 border-t border-neutral-200 pt-5">
      <div className="flex flex-wrap items-center justify-between gap-4"><h2 className="flex items-center gap-2 text-xl font-semibold"><Truck/>{t('route')} #{trip.id} · {trip.date}</h2><span>{t(trip.status)}</span></div>
      <div className="flex gap-3">{trip.status==='planned'&&<Button className="min-h-14" disabled={pending.some(op=>op.path===`/trips/${trip.id}/start`)} onClick={()=>void enqueue(`/trips/${trip.id}/start`)}>{t('start')}</Button>}{trip.status==='in_progress'&&<Button variant="secondary" className="min-h-14" onClick={()=>void enqueue(`/trips/${trip.id}/finish`)}>{t('finish')}</Button>}</div>
      <ol className="divide-y divide-neutral-200">{trip.stops.map(stop=>{
        const waiting=pending.some(op=>op.path.startsWith(`/trip-stops/${stop.id}/`))
        return <li key={stop.id} className="space-y-4 py-5">
          <div className="flex items-start gap-3"><MapPin className="mt-1 shrink-0"/><div><h3 className="text-xl font-semibold">{stop.sequence}. {stop.contact_name??(stop.type==='customer'?t('customer'):t('branch'))}</h3><p className="text-lg">{stop.address??'—'}</p></div><span className="ml-auto text-sm">{waiting?t('saved'):stop.status==='delivered'?t('stopDelivered'):stop.status==='failed'?t('stopFailed'):t('stopPending')}</span></div>
          <p className="text-xl font-semibold text-red-800">{t('collect')}: {amount(stop.cash_to_collect)}</p>
          <div className="flex flex-wrap gap-3">{(stop.address||stop.lat)&&<a className="inline-flex min-h-14 items-center gap-2 rounded-lg border border-neutral-300 px-5 font-semibold" href={`https://yandex.com/maps/?${stop.lat&&stop.lng?`rtext=~${stop.lat},${stop.lng}&rtt=auto`:`text=${encodeURIComponent(stop.address??'')}`}`} target="_blank" rel="noreferrer"><Navigation size={20}/>{t('navigator')}</a>}{stop.contact_phone&&<a className="inline-flex min-h-14 items-center gap-2 rounded-lg border border-neutral-300 px-5 font-semibold" href={`tel:${stop.contact_phone}`}><Phone size={20}/>{t('phone')}</a>}</div>
          {trip.status==='in_progress'&&['pending','arrived'].includes(stop.status)&&!waiting&&<>
            <label className="flex max-w-sm items-center gap-2 text-sm"><Camera size={18}/>{t('photo')}<input className="min-w-0" type="file" accept="image/jpeg,image/png,image/webp" capture="environment" onChange={e=>void readPhoto(stop.id,e.target.files?.[0])}/></label>
            <div className="flex flex-wrap gap-4"><Button className="min-h-16 min-w-36 text-lg" disabled={busy!==null} onClick={()=>void deliver(stop)}>{busy===stop.id?t('gps'):t('delivered')}</Button><Button variant="secondary" className="min-h-16 min-w-36" onClick={()=>{setReasonStop(stop.id);setReason('')}}>{t('fail')}</Button></div>
            {reasonStop===stop.id&&<form className="flex flex-wrap items-end gap-3" onSubmit={e=>{e.preventDefault();void enqueue(`/trip-stops/${stop.id}/fail`,{reason});setReasonStop(null)}}><Input label={t('reason')} required minLength={3} maxLength={2000} value={reason} onChange={e=>setReason(e.target.value)}/><Button>{t('save')}</Button><Button type="button" variant="ghost" onClick={()=>setReasonStop(null)}>{t('cancel')}</Button></form>}
          </>}
        </li>
      })}</ol>
    </section>)}
  </div>
}

function DispatchForms({trips,onSaved}:{trips:Trip[];onSaved:()=>void}) {
  const t=useDeliveryCopy();const permissions=useSession(s=>s.permissions)
  const [error,setError]=useState('');const [busy,setBusy]=useState(false)
  const [point,setPoint]=useState({lat:'',lng:''})
  const users=useQuery({queryKey:['delivery-users'],queryFn:()=>api.get<List<User>>('/users',{params:{per_page:100}}),enabled:permissions.includes('core.user.view_any')})
  const branches=useQuery({queryKey:['delivery-branches'],queryFn:()=>api.get<List<{id:number;name:string}>>('/branches',{params:{per_page:100}})})
  async function post(path:string,body:Record<string,unknown>) {
    setBusy(true);setError('')
    try {await api.post(path,{body,idempotencyKey:idempotencyKey()});onSaved()} catch(e){setError(errorText(e))} finally{setBusy(false)}
  }
  const get=(form:HTMLFormElement)=>Object.fromEntries(new FormData(form))
  return <details className="rounded-xl border border-neutral-200 p-4"><summary className="cursor-pointer font-semibold">{t('newTrip')} / {t('newStop')} / {t('collection')}</summary>
    {error&&<p className="py-3 text-red-800" role="alert">{error}</p>}
    <form className="mt-5 grid gap-3 sm:grid-cols-3" onSubmit={e=>{e.preventDefault();void post('/trips',get(e.currentTarget))}}><Select name="driver_id" label={t('driver')} required><option value="">{t('choose')}</option>{users.data?.data.filter(u=>u.roles?.includes('driver')).map(u=><option key={u.id} value={u.id}>{u.name}</option>)}</Select><Input name="date" type="date" label={t('date')} defaultValue={today()} required/><Button className="self-end" disabled={busy}>{t('newTrip')}</Button></form>
    <form className="mt-6 grid gap-3 sm:grid-cols-3" onSubmit={e=>{e.preventDefault();const {trip_id,...body}=get(e.currentTarget);void post(`/trips/${trip_id}/stops`,Object.fromEntries(Object.entries(body).filter(([,v])=>v!=='')))}}>
      <Select name="trip_id" label={t('route')} required><option value="">{t('choose')}</option>{trips.filter(v=>v.status==='planned').map(v=><option key={v.id} value={v.id}>#{v.id} · {v.date}</option>)}</Select><Select name="type" label={t('type')}><option value="customer">{t('customer')}</option><option value="branch">{t('branch')}</option></Select><Input name="order_id" label={`${t('order')} №`} type="number" min="1"/><Input name="transfer_id" label={`${t('transfer')} №`} type="number" min="1"/><Input name="address" label={t('address')} maxLength={255}/><Input name="cash_to_collect" label={t('collect')} type="number" min="0" step="0.01"/><MapPicker onSelect={(lat,lng)=>setPoint({lat,lng})}/><Input name="lat" label={t('lat')} type="number" min="-90" max="90" step="any" value={point.lat} onChange={e=>setPoint({...point,lat:e.target.value})}/><Input name="lng" label={t('lng')} type="number" min="-180" max="180" step="any" value={point.lng} onChange={e=>setPoint({...point,lng:e.target.value})}/><Button className="self-end" disabled={busy}>{t('newStop')}</Button>
    </form>
    {permissions.includes('delivery.collection.create')&&<form className="mt-6 grid gap-3 sm:grid-cols-4" onSubmit={e=>{e.preventDefault();void post('/collections',get(e.currentTarget))}}><Select name="driver_id" label={t('driver')} required><option value="">{t('choose')}</option>{users.data?.data.filter(u=>u.roles?.includes('driver')).map(u=><option key={u.id} value={u.id}>{u.name}</option>)}</Select><Select name="branch_id" label={t('branch')} required><option value="">{t('choose')}</option>{branches.data?.data.map(v=><option key={v.id} value={v.id}>{v.name}</option>)}</Select><Input name="amount" label={t('amount')} required type="number" min="0.01" step="0.01"/><Button className="self-end" disabled={busy}>{t('collection')}</Button></form>}
  </details>
}
