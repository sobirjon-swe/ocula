import { useEffect, useState } from 'react'
import { useT } from '@/lib/i18n'
export function ConnectionStatus() {
 const t = useT()
 const [online,setOnline] = useState(navigator.onLine)
 useEffect(() => {
 const update=()=>setOnline(navigator.onLine)
 window.addEventListener('online',update); window.addEventListener('offline',update)
 return ()=>{window.removeEventListener('online',update);window.removeEventListener('offline',update)}
 },[])
 return online ? null : <p role="status" className="bg-amber-50 p-3 text-sm text-amber-900">{t('app.offline')}</p>
}
