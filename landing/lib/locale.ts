import type { Metadata } from 'next'
import { notFound } from 'next/navigation'
import { getCopy, isLocale, locales, type LocaleProps } from './i18n'
import { site } from './site'
export async function pageLocale(props:LocaleProps) { const {locale}=await props.params;if(!isLocale(locale))notFound();return {locale,t:getCopy(locale)} }
export async function pageMetadata(props:LocaleProps,path:string,title:'tagline'|'branches'|'priceTitle'|'booking',description:'description'|'branchDescription'|'priceDescription'|'bookingDescription'):Promise<Metadata> {
 const {locale,t}=await pageLocale(props);return {title:t[title],description:t[description],alternates:{canonical:site.url+'/'+locale+path,languages:Object.fromEntries(locales.map(l=>[l,site.url+'/'+l+path]))},openGraph:{title:t[title],description:t[description],url:site.url+'/'+locale+path,locale:locale==='ru'?'ru_RU':locale==='en'?'en_US':'uz_UZ'}}
}
