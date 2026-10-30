import { pageLocale, pageMetadata } from "@/lib/locale";
import type { LocaleProps } from "@/lib/i18n";
import { BookingForm } from "@/components/BookingForm";
import { getBranches } from "@/lib/api";

export const generateMetadata=(props:LocaleProps)=>pageMetadata(props,"/booking","booking","bookingDescription");

export default async function BookingPage(props:LocaleProps) {
  const {locale,t}=await pageLocale(props);
  const branches = await getBranches();

  return (
    <div className="mx-auto max-w-xl px-6 py-16">
      <h1 className="text-3xl font-semibold tracking-tight text-zinc-950 dark:text-zinc-50">
        {t.booking}
      </h1>
      <p className="mt-3 text-sm text-zinc-600 dark:text-zinc-400">
        {t.bookingIntro}
      </p>

      <div className="mt-10">
        <BookingForm branches={branches} locale={locale} />
      </div>
    </div>
  );
}
