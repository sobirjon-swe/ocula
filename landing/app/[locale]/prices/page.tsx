import { pageLocale, pageMetadata } from "@/lib/locale";
import type { LocaleProps } from "@/lib/i18n";
import { getServices } from "@/lib/api";
import type { Service } from "@/lib/types";

export const generateMetadata=(props:LocaleProps)=>pageMetadata(props,"/prices","priceTitle","priceDescription");



export default async function PricesPage(props:LocaleProps) {
  const {locale,t}=await pageLocale(props);
  const services = await getServices(locale);
  const groups = groupByType(services);

  return (
    <div className="mx-auto max-w-5xl px-6 py-16">
      <h1 className="text-3xl font-semibold tracking-tight text-zinc-950 dark:text-zinc-50">
        {t.priceTitle}
      </h1>

      {services.length === 0 ? (
        <p className="mt-6 text-zinc-600 dark:text-zinc-400">
          {t.emptyPrices}
        </p>
      ) : (
        <div className="mt-10 space-y-10">
          {groups.map(([type, items]) => (
            <section key={type}>
              <h2 className="text-lg font-medium text-zinc-950 dark:text-zinc-50">
                {({exam:t.exam,assembly:t.assembly,repair:t.repair})[type]}
              </h2>
              <ul className="mt-4 divide-y divide-black/10 dark:divide-white/10">
                {items.map((service) => (
                  <li
                    key={service.id}
                    className="flex items-center justify-between gap-4 py-3 text-sm"
                  >
                    <span className="text-zinc-700 dark:text-zinc-300">{service.name}</span>
                    <span className="shrink-0 font-medium text-zinc-950 dark:text-zinc-50">
                      {service.price_formatted}
                    </span>
                  </li>
                ))}
              </ul>
            </section>
          ))}
        </div>
      )}
    </div>
  );
}

function groupByType(services: Service[]): Array<[Service["type"], Service[]]> {
  const groups = new Map<Service["type"], Service[]>();

  for (const service of services) {
    const bucket = groups.get(service.type) ?? [];
    bucket.push(service);
    groups.set(service.type, bucket);
  }

  return Array.from(groups.entries());
}
