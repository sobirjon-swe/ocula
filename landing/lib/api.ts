import type { ApiEnvelope, AppointmentPayload, Branch, Service } from "@/lib/types";
import { getCopy, isLocale } from "@/lib/i18n";

/**
 * Backend bilan bog'lanish — faqat server tomonda (Server Component,
 * Route Handler). Bu fetch chaqiruvlari hech qachon brauzerda
 * ishlamaydi, shuning uchun CORS umuman kerak emas (BOSQICH-11.md).
 */
const BACKEND_API_URL = process.env.BACKEND_API_URL ?? "http://localhost:8000/api/v1";

async function getJson<T>(path: string, revalidateSeconds: number, locale = "uz-latn"): Promise<T> {
  const response = await fetch(`${BACKEND_API_URL}${path}`, {
    next: { revalidate: revalidateSeconds },
    headers: {"Accept-Language": locale},
    signal: AbortSignal.timeout(10000),
  });

  if (!response.ok) {
    throw new Error(`Backend so'rovi muvaffaqiyatsiz: ${path} (${response.status})`);
  }

  const envelope = (await response.json()) as ApiEnvelope<T>;
  return envelope.data;
}

/** Filiallar (manzillar) — 5 daqiqada bir yangilanadi. */
export function getBranches(): Promise<Branch[]> {
  return getJson<Branch[]>("/public/branches", 300);
}

/** Xizmat narxlari — 5 daqiqada bir yangilanadi. */
export function getServices(locale = "uz-latn"): Promise<Service[]> {
  return getJson<Service[]>("/public/services", 300, locale);
}

export type SubmitAppointmentResult =
  | { ok: true }
  | { ok: false; message: string; status?:number; retryAfter?:string };

/** Onlayn navbat so'rovini backendga yuboradi. */
export async function submitAppointment(
  payload: AppointmentPayload,
  locale = "uz-latn",
): Promise<SubmitAppointmentResult> {
  const response = await fetch(`${BACKEND_API_URL}/public/appointments`, {
    method: "POST",
    headers: { "Content-Type": "application/json", "Accept-Language": locale },
    body: JSON.stringify(payload),
    cache: "no-store",
    signal: AbortSignal.timeout(15000),
  });

  if (response.ok) {
    return { ok: true };
  }

  if (response.status === 422 || response.status === 429) {
    const body = await response.json().catch(()=>({})) as { message?: string };
    return { ok: false, message: body.message ?? getCopy(isLocale(locale)?locale:'uz-latn').invalid, status:response.status, retryAfter:response.headers.get('Retry-After')??undefined };
  }

  return { ok: false, message: getCopy(isLocale(locale)?locale:'uz-latn').error, status:503 };
}
