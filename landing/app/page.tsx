import Link from "next/link";
import { site } from "@/lib/site";

export default function Home() {
  return (
    <div className="mx-auto max-w-5xl px-6 py-20">
      <section className="max-w-2xl">
        <h1 className="text-4xl font-semibold tracking-tight text-zinc-950 sm:text-5xl dark:text-zinc-50">
          {site.tagline}
        </h1>
        <p className="mt-6 text-lg text-zinc-600 dark:text-zinc-400">
          {site.description}
        </p>

        <div className="mt-10 flex flex-wrap gap-4">
          <Link
            href="/booking"
            className="rounded-full bg-zinc-950 px-6 py-3 text-sm font-medium text-white transition-colors hover:bg-zinc-800 dark:bg-zinc-50 dark:text-zinc-950 dark:hover:bg-zinc-200"
          >
            Onlayn navbat olish
          </Link>
          <Link
            href="/branches"
            className="rounded-full border border-black/10 px-6 py-3 text-sm font-medium text-zinc-950 transition-colors hover:bg-black/5 dark:border-white/15 dark:text-zinc-50 dark:hover:bg-white/10"
          >
            Filiallarni ko'rish
          </Link>
        </div>
      </section>

      <section className="mt-24 grid gap-8 sm:grid-cols-3">
        <Feature
          title="Ko'z tekshiruvi"
          description="Tajribali shifokorlar tomonidan to'liq ko'rish diagnostikasi."
        />
        <Feature
          title="Keng assortiment"
          description="Zamonaviy ko'zoynak ramkalari va linzalar."
        />
        <Feature
          title="Onlayn navbat"
          description="Filialga bormasdan turib navbat oling — biz o'zimiz qo'ng'iroq qilamiz."
        />
      </section>
    </div>
  );
}

function Feature({ title, description }: { title: string; description: string }) {
  return (
    <div>
      <h2 className="font-medium text-zinc-950 dark:text-zinc-50">{title}</h2>
      <p className="mt-2 text-sm text-zinc-600 dark:text-zinc-400">{description}</p>
    </div>
  );
}
