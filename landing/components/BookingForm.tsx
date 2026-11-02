"use client";

import { useState, type FormEvent } from "react";
import { getCopy, type Locale } from "@/lib/i18n";
import type { Branch } from "@/lib/types";

type Status = { kind: "idle" } | { kind: "sending" } | { kind: "sent" } | { kind: "error"; message: string };

export function BookingForm({ branches, locale }: { branches: Branch[]; locale:Locale }) {
  const t=getCopy(locale);
  const [status, setStatus] = useState<Status>({ kind: "idle" });

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if(status.kind === "sending") return;
    const element=event.currentTarget;
    setStatus({ kind: "sending" });

    const form = new FormData(element);
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
        body: JSON.stringify(payload),
        signal: AbortSignal.timeout(20000),
        headers: { "Content-Type": "application/json", "Accept-Language":locale },
      });
      const result = (await response.json()) as { ok: boolean; message?: string };

      if (result.ok) {
        setStatus({ kind: "sent" });
        element.reset();
      } else {
        setStatus({ kind: "error", message: result.message ?? t.error });
      }
    } catch {
      setStatus({ kind: "error", message: t.error });
    }
  }

  if (status.kind === "sent") {
    return (
      <div role="status" className="rounded-2xl border border-black/10 p-6 text-sm dark:border-white/10">
        {t.sent}
      </div>
    );
  }

  return (
    <form aria-busy={status.kind === "sending"} onSubmit={handleSubmit} className="space-y-5">
      {branches.length === 0 && <p role="status">{t.emptyBranches}</p>}
      <Field label={t.branch}>
        <select
          name="branch_id"
          required
          defaultValue=""
          className="w-full rounded-lg border border-black/10 bg-transparent px-3 py-2 text-base dark:border-white/15"
        >
          <option value="" disabled>
            {t.selectBranch}
          </option>
          {branches.map((branch) => (
            <option key={branch.id} value={branch.id}>
              {branch.name}
            </option>
          ))}
        </select>
      </Field>

      <Field label={t.name}>
        <input
          autoComplete="name"
          name="name"
          type="text"
          required
          maxLength={120}
          className="w-full rounded-lg border border-black/10 bg-transparent px-3 py-2 text-sm dark:border-white/15"
        />
      </Field>

      <Field label={t.phone}>
        <input
          autoComplete="tel"
          pattern="[+0-9 ()-]{7,32}"
          name="phone"
          type="tel"
          required
          maxLength={32}
          placeholder="+998 90 123 45 67"
          className="w-full rounded-lg border border-black/10 bg-transparent px-3 py-2 text-sm dark:border-white/15"
        />
      </Field>

      <Field label={t.date}>
        <input
          name="preferred_date"
          type="date"
          min={new Intl.DateTimeFormat("en-CA",{timeZone:"Asia/Tashkent"}).format(new Date())}
          className="w-full rounded-lg border border-black/10 bg-transparent px-3 py-2 text-sm dark:border-white/15"
        />
      </Field>

      <Field label={t.note}>
        <textarea
          name="note"
          rows={3}
          maxLength={1000}
          className="w-full rounded-lg border border-black/10 bg-transparent px-3 py-2 text-sm dark:border-white/15"
        />
      </Field>

      {status.kind === "error" ? (
        <p role="alert" className="text-sm text-red-600 dark:text-red-400">{status.message}</p>
      ) : null}

      <button
        type="submit"
        disabled={status.kind === "sending" || branches.length === 0}
        className="w-full rounded-full bg-zinc-950 px-6 py-3 text-sm font-medium text-white transition-colors hover:bg-zinc-800 disabled:opacity-60 dark:bg-zinc-50 dark:text-zinc-950 dark:hover:bg-zinc-200"
      >
        {status.kind === "sending" ? t.sending : t.send}
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
