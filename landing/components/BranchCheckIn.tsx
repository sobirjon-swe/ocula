import Image from 'next/image';
import QRCode from 'qrcode';
import { PrintQrButton } from './PrintQrButton';
import type { Copy } from '@/lib/i18n';

export async function BranchCheckIn({id,name,t}:{id:number;name:string;t:Copy}) {
  const environment=process.env;
  const miniApp=environment.TELEGRAM_MINI_APP_URL;
  const customer=environment.CUSTOMER_APP_URL??environment.NEXT_PUBLIC_CUSTOMER_URL;
  if(!miniApp&&!customer)return null;
  let url:URL;
  try {
    url=miniApp?new URL(miniApp):new URL('/check-in',customer);
    if(!['https:','http:'].includes(url.protocol))return null;
    url.searchParams.set(miniApp?'startapp':'branch',miniApp?'branch_'+id:String(id));
  } catch { return null; }
  const href=url.toString();
  const image=await QRCode.toDataURL(href,{width:384,margin:4,errorCorrectionLevel:'M'});
  return <section className="mt-6 border-t border-black/10 pt-5 dark:border-white/10">
    <h3 className="font-medium">{t.queueTitle}</h3>
    <p className="mt-2 text-sm text-zinc-600 dark:text-zinc-400">{t.queueDescription}</p>
    <Image className="mt-4 rounded-lg" unoptimized src={image} width={192} height={192} alt={t.queueTitle+' — '+name}/>
    <div className="mt-3 flex flex-wrap items-center gap-5 text-sm"><a className="min-h-11 py-3 underline underline-offset-4" href={href}>{t.queueOpen}</a><PrintQrButton image={image} title={'OPTIKA — '+name+' — '+t.queueTitle} url={href} label={t.queuePrint}/></div>
  </section>
}
