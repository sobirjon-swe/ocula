import type { Metadata } from "next";
import { BookingForm } from "@/components/BookingForm";
import { getBranches } from "@/lib/api";

export const metadata: Metadata = {
  title: "Onlayn navbat",
  description: "Filialga bormasdan turib onlayn navbat oling — biz o'zimiz qo'ng'iroq qilamiz.",
};

export default async function BookingPage() {
  const branches = await getBranches();

  return (
    <div className="mx-auto max-w-xl px-6 py-16">
      <h1 className="text-3xl font-semibold tracking-tight text-zinc-950 dark:text-zinc-50">
        Onlayn navbat
      </h1>
      <p className="mt-3 text-sm text-zinc-600 dark:text-zinc-400">
        Ma'lumotlaringizni qoldiring — filial xodimi siz bilan bog'lanib, qulay vaqtga
        navbat rasmiylashtiradi.
      </p>

      <div className="mt-10">
        <BookingForm branches={branches} />
      </div>
    </div>
  );
}
