'use client'
import { useParams } from 'next/navigation'
import { getCopy, isLocale } from '@/lib/i18n'
export default function ErrorPage({reset}:{reset:()=>void}) {const params=useParams();const locale=typeof params.locale==='string'&&isLocale(params.locale)?params.locale:'uz-latn';const t=getCopy(locale);return <div role="alert" className="mx-auto max-w-xl px-6 py-16"><h1 className="text-xl font-semibold">{t.unavailable}</h1><button className="mt-6 rounded-lg border px-5 py-3 underline" onClick={reset}>{t.retry}</button></div>}
