import { NextResponse } from 'next/server'
import { submitAppointment } from '@/lib/api'
import { getCopy, isLocale } from '@/lib/i18n'
import type { AppointmentPayload } from '@/lib/types'
export async function POST(request: Request) {
 const language=request.headers.get('Accept-Language')??'uz-latn';const locale=isLocale(language)?language:'uz-latn';const t=getCopy(locale)
 let body:Partial<AppointmentPayload>
 try { body=await request.json() } catch { return NextResponse.json({ok:false,message:t.invalid},{status:400}) }
 if(!body || !Number.isSafeInteger(body.branch_id) || Number(body.branch_id)<1 || typeof body.name!=='string' || !body.name.trim() || body.name.length>120 || typeof body.phone!=='string' || !/^[+0-9 ()-]{7,32}$/.test(body.phone) || (body.note!==undefined && (typeof body.note!=='string'||body.note.length>1000)) || (body.preferred_date!==undefined && (typeof body.preferred_date!=='string'||!/^\d{4}-\d{2}-\d{2}$/.test(body.preferred_date)))) return NextResponse.json({ok:false,message:t.invalid},{status:422})
 try {
 const result=await submitAppointment({branch_id:body.branch_id!,name:body.name.trim(),phone:body.phone.trim(),preferred_date:body.preferred_date,note:body.note},locale)
 return NextResponse.json(result,{status:result.ok?201:result.status??422,headers:!result.ok&&result.retryAfter?{'Retry-After':result.retryAfter}:{}})
 } catch { return NextResponse.json({ok:false,message:t.error},{status:503}) }
}
