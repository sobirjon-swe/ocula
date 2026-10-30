import Link from "next/link";
import { getCopy, locales, type Locale } from "@/lib/i18n";
import { site } from "@/lib/site";



export function Header({locale}: {locale:Locale}) {
  const t=getCopy(locale); const NAV=[{href:"/branches",label:t.branches},{href:"/prices",label:t.prices},{href:"/booking",label:t.booking}];
  return (
    <header className="border-b border-black/10 dark:border-white/10">
      <div className="mx-auto flex max-w-5xl flex-wrap gap-4 items-center justify-between px-6 py-4">
        <Link href={`/${locale}`} className="text-lg font-semibold tracking-tight">
          {site.name}
        </Link>

        <nav className="flex items-center flex-wrap gap-4 text-sm">
          {NAV.map((item) => (
            <Link
              key={item.href}
              href={`/${locale}${item.href}`}
              className="text-zinc-600 transition-colors hover:text-zinc-950 dark:text-zinc-400 dark:hover:text-zinc-50"
            >
              {item.label}
            </Link>
          ))}
        </nav>
        <nav aria-label={t.language} className="flex flex-wrap gap-3 text-sm">{locales.map(l=><Link key={l} href={`/${l}`} hrefLang={l} lang={l} aria-current={l===locale?"true":undefined} className={l===locale?"font-semibold underline":"text-zinc-600 dark:text-zinc-400"}>{({"uz-latn":"O‘zbekcha","uz-cyrl":"Ўзбекча",ru:"Русский",en:"English"})[l]}</Link>)}</nav>
      </div>
    </header>
  );
}
