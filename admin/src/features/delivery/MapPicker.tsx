import { useEffect, useRef, useState } from 'react'
import { useSession } from '@/lib/session'
import { useDeliveryCopy } from './copy'

type Coordinates = [number, number]
type MapEvent = { get: (name: string) => unknown }
type MapInstance = { events: { add:(name:string,fn:(event:MapEvent)=>void)=>void }; geoObjects:{removeAll:()=>void;add:(mark:unknown)=>void};destroy:()=>void }
type Yandex = { ready:(fn:()=>void)=>void;Map:new(element:HTMLElement,state:{center:Coordinates;zoom:number;controls:string[]})=>MapInstance;Placemark:new(coords:Coordinates)=>unknown }
declare global { interface Window { ymaps?:Yandex } }
let loading:Promise<Yandex>|undefined
function loadMaps(key:string):Promise<Yandex> {
  if(window.ymaps) return Promise.resolve(window.ymaps)
  loading??=new Promise((resolve,reject)=>{
    const script=document.createElement('script')
    script.src=`https://api-maps.yandex.ru/2.1/?apikey=${encodeURIComponent(key)}&lang=ru_RU`
    script.async=true
    script.onload=()=>window.ymaps?resolve(window.ymaps):reject(new Error('Map unavailable'))
    script.onerror=()=>{loading=undefined;reject(new Error('Map unavailable'))}
    document.head.appendChild(script)
  })
  return loading
}

export function MapPicker({onSelect}:{onSelect:(lat:string,lng:string)=>void}) {
  const t=useDeliveryCopy()
  const target=useRef<HTMLDivElement>(null)
  const select=useRef(onSelect)
  const locale=useSession(s=>s.locale)
  const [failed,setFailed]=useState(false)
  const key=import.meta.env.VITE_YANDEX_MAPS_KEY as string|undefined
  useEffect(()=>{select.current=onSelect},[onSelect])
  useEffect(()=>{
    if(!key||!target.current) return
    let disposed=false;let map:MapInstance|undefined
    void loadMaps(key).then(ymaps=>ymaps.ready(()=>{
      if(disposed||!target.current) return
      map=new ymaps.Map(target.current,{center:[41.3111,69.2797],zoom:12,controls:['zoomControl','searchControl','geolocationControl']})
      map.events.add('click',event=>{
        const coords=event.get('coords')
        if(!Array.isArray(coords)||coords.length!==2||!coords.every(Number.isFinite)) return
        const point:Coordinates=[Number(coords[0]),Number(coords[1])]
        map?.geoObjects.removeAll();map?.geoObjects.add(new ymaps.Placemark(point))
        select.current(point[0].toFixed(6),point[1].toFixed(6))
      })
    })).catch(()=>{if(!disposed)setFailed(true)})
    return ()=>{disposed=true;map?.destroy()}
  },[key])
  return <div className="space-y-2 sm:col-span-3">
    <div className="flex items-center gap-4"><span className="text-sm font-medium">{t('map')}</span><a className="text-brand-700 underline" href={`https://yandex.com/maps/?lang=${locale==='en'?'en_US':'ru_RU'}`} target="_blank" rel="noreferrer">Yandex Maps</a></div>
    {key&&!failed&&<div ref={target} className="h-80 overflow-hidden rounded-xl border border-neutral-200" aria-label={t('map')}/>}
  </div>
}
