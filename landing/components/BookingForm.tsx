"use client";

import { useState, type FormEvent } from "react";
import type { Branch } from "@/lib/types";

type Status = { kind: "idle" } | { kind: "sending" } | { kind: "sent" } | { kind: "error"; message: string };

export function BookingForm({ branches }: { branches: Branch[] }) {
  const [status, setStatus] = useState<Status>({ kind: "idle" });

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setStatus({ kind: "sending" });

    const form = new FormData(event.currentTarget);
    const payload = {
      branch_id: Number(form.get("branch_id")),
      name: String(form.get("name") ?? ""),
      phone: String(form.get("phone") ?? ""),
      preferred_date: String(form.get("preferred_date") ?? "") || undefined,
      note: String(form.get("note") ?? "") || undefined,
    };

    try {
      const response = await fetch("/api/appointments", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload),
      });
      const result = (await response.json()) as { ok: boolean; message?: string };

      if (result.ok) {
        setStatus({ kind: "sent" });
        event.currentTarget.reset();
      } else {
        setStatus({ kind: "error", message: result.message ?? "Xatolik yuz berdi." });
      }
    } catch {
      setStatus({ kind: "error", message: "Server bilan bog'lanib bo'lmadi." });
    }
  }

  if (status.kind === "sent") {
    return (
      <div className="rounded-2xl border border-black/10 p-6 text-sm dark:border-white/10">
        Arizangiz qabul qilindi! Tez orada sizga qo'ng'iroq qilamiz.
      </div>
    );
  }

  return (
    <form onSubmit={handleSubmit} className="space-y-5">
      <Field label="Filial">
        <select
          name="branch_id"
          required
          defaultValue=""
          className="w-full rounded-lg border border-black/10 bg-transparent px-3 py-2 text-sm dark:border-white/15"
        >
          <option value="" disabled>
            Filialni tanlang
          </option>
          {branches.map((branch) => (
            <option key={branch.id} value={branch.id}>
              {branch.name}
            </option>
          ))}
        </select>
      </Field>

      <Field label="Ismingiz">
        <input
          name="name"
          type="text"
          required
          maxLength={120}
          className="w-full rounded-lg border border-black/10 bg-transparent px-3 py-2 text-sm dark:border-white/15"
        />
      </Field>

      <Field label="Telefon raqamingiz">
        <input
          name="phone"
          type="tel"
          required
          maxLength={32}
          placeholder="+998 90 123 45 67"
          className="w-full rounded-lg border border-black/10 bg-transparent px-3 py-2 text-sm dark:border-white/15"
        />
      </Field>

      <Field label="Qulay sana (ixtiyoriy)">
        <input
          name="preferred_date"
          type="date"
          className="w-full rounded-lg border border-black/10 bg-transparent px-3 py-2 text-sm dark:border-white/15"
        />
      </Field>

      <Field label="Izoh (ixtiyoriy)">
        <textarea
          name="note"
          rows={3}
          maxLength={1000}
          className="w-full rounded-lg border border-black/10 bg-transparent px-3 py-2 text-sm dark:border-white/15"
        />
      </Field>

      {status.kind === "error" ? (
        <p className="text-sm text-red-600 dark:text-red-400">{status.message}</p>
      ) : null}

      <button
        type="submit"
        disabled={status.kind === "sending"}
        className="w-full rounded-full bg-zinc-950 px-6 py-3 text-sm font-medium text-white transition-colors hover:bg-zinc-800 disabled:opacity-60 dark:bg-zinc-50 dark:text-zinc-950 dark:hover:bg-zinc-200"
      >
        {status.kind === "sending" ? "Yuborilmoqda…" : "Navbat olish"}
      </button>
    </form>
  );
}

function Field({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <label className="block">
      <span className="mb-1.5 block text-sm font-medium text-zinc-700 dark:text-zinc-300">
        {label}
      </span>
      {children}
    </label>
  );
}
