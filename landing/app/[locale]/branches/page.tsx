import { BranchCheckIn } from '@/components/BranchCheckIn';
import { pageLocale, pageMetadata } from "@/lib/locale";
import type { LocaleProps } from "@/lib/i18n";
import { getBranches } from "@/lib/api";

export const generateMetadata=(props:LocaleProps)=>pageMetadata(props,"/branches","branches","branchDescription");

export default async function BranchesPage(props:LocaleProps) {
  const {t}=await pageLocale(props);
  const branches = await getBranches();

  return (
    <div className="mx-auto max-w-5xl px-6 py-16">
      <h1 className="text-3xl font-semibold tracking-tight text-zinc-950 dark:text-zinc-50">
        {t.branches}
      </h1>

      {branches.length === 0 ? (
        <p className="mt-6 text-zinc-600 dark:text-zinc-400">
          {t.emptyBranches}
        </p>
      ) : (
        <ul className="mt-10 grid gap-6 sm:grid-cols-2">
          {branches.map((branch) => (
            <li
              key={branch.id}
              className="rounded-2xl border border-black/10 p-6 dark:border-white/10"
            >
              <h2 className="font-medium text-zinc-950 dark:text-zinc-50">{branch.name}</h2>

              {branch.address ? (
                <p className="mt-2 text-sm text-zinc-600 dark:text-zinc-400">{branch.address}</p>
              ) : null}

              {branch.phone ? (
                <p className="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
                  <a href={`tel:${branch.phone}`} className="hover:underline">
                    {branch.phone}
                  </a>
                </p>
              ) : null}

              {(branch.lat !== null && branch.lng !== null || branch.address) && <p className="mt-3 text-sm"><a className="underline underline-offset-4" target="_blank" rel="noopener noreferrer" href={branch.lat !== null && branch.lng !== null ? 'https://yandex.uz/maps/?pt='+encodeURIComponent(branch.lng+','+branch.lat)+'&z=16&l=map' : 'https://yandex.uz/maps/?text='+encodeURIComponent(branch.address!)}>{t.map}</a></p>}
              {branch.open_time && branch.close_time ? (
                <p className="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
                  {branch.open_time.slice(0, 5)}–{branch.close_time.slice(0, 5)}
                </p>
              ) : null}
              <BranchCheckIn id={branch.id} name={branch.name} t={t}/>
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}
