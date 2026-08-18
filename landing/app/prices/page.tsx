import type { Metadata } from "next";
import { getServices } from "@/lib/api";
import type { Service } from "@/lib/types";

export const metadata: Metadata = {
  title: "Narxlar",
  description: "Ko'z tekshiruvi, ko'zoynak montaji va boshqa xizmatlarimiz narxlari.",
};

const TYPE_LABELS: Record<Service["type"], string> = {
  exam: "Ko'z tekshiruvi",
  assembly: "Montaj",
  repair: "Ta'mirlash",
};

export default async function PricesPage() {
  const services = await getServices();
  const groups = groupByType(services);

  return (
    <div className="mx-auto max-w-5xl px-6 py-16">
      <h1 className="text-3xl font-semibold tracking-tight text-zinc-950 dark:text-zinc-50">
        Xizmat narxlari
      </h1>

      {services.length === 0 ? (
        <p className="mt-6 text-zinc-600 dark:text-zinc-400">
          Hozircha narxlar ro'yxati mavjud emas.
        </p>
      ) : (
        <div className="mt-10 space-y-10">
          {groups.map(([type, items]) => (
            <section key={type}>
              <h2 className="text-lg font-medium text-zinc-950 dark:text-zinc-50">
                {TYPE_LABELS[type]}
              </h2>
              <ul className="mt-4 divide-y divide-black/10 dark:divide-white/10">
                {items.map((service) => (
                  <li
                    key={service.id}
                    className="flex items-center justify-between py-3 text-sm"
                  >
                    <span className="text-zinc-700 dark:text-zinc-300">{service.name}</span>
                    <span className="font-medium text-zinc-950 dark:text-zinc-50">
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
