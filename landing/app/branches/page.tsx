import type { Metadata } from "next";
import { getBranches } from "@/lib/api";

export const metadata: Metadata = {
  title: "Filiallar",
  description: "Barcha filiallarimizning manzili, telefon raqami va ish vaqti.",
};

export default async function BranchesPage() {
  const branches = await getBranches();

  return (
    <div className="mx-auto max-w-5xl px-6 py-16">
      <h1 className="text-3xl font-semibold tracking-tight text-zinc-950 dark:text-zinc-50">
        Filiallar
      </h1>

      {branches.length === 0 ? (
        <p className="mt-6 text-zinc-600 dark:text-zinc-400">
          Hozircha filiallar ro'yxati mavjud emas.
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

              {branch.open_time && branch.close_time ? (
                <p className="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
                  {branch.open_time.slice(0, 5)}–{branch.close_time.slice(0, 5)}
                </p>
              ) : null}
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}
