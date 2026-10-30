import { Header } from '@/components/Header'
import { Footer } from '@/components/Footer'
import { pageLocale } from '@/lib/locale'
import type { LocaleProps } from '@/lib/i18n'
export default async function Layout(props:LocaleProps & {children:React.ReactNode}) { const {locale,t}=await pageLocale(props);return <div lang={locale} className="flex min-h-screen flex-col"><a href="#content" className="sr-only focus:not-sr-only focus:p-4">{t.skip}</a><Header locale={locale}/><main id="content" className="flex-1">{props.children}</main><Footer locale={locale}/></div> }
