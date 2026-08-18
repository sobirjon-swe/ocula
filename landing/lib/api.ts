import type { ApiEnvelope, AppointmentPayload, Branch, Service } from "@/lib/types";

/**
 * Backend bilan bog'lanish — faqat server tomonda (Server Component,
 * Route Handler). Bu fetch chaqiruvlari hech qachon brauzerda
 * ishlamaydi, shuning uchun CORS umuman kerak emas (BOSQICH-11.md).
 */
const BACKEND_API_URL = process.env.BACKEND_API_URL ?? "http://localhost:8000/api/v1";

async function getJson<T>(path: string, revalidateSeconds: number): Promise<T> {
  const response = await fetch(`${BACKEND_API_URL}${path}`, {
    next: { revalidate: revalidateSeconds },
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
export function getServices(): Promise<Service[]> {
  return getJson<Service[]>("/public/services", 300);
}

export type SubmitAppointmentResult =
  | { ok: true }
  | { ok: false; message: string };

/** Onlayn navbat so'rovini backendga yuboradi. */
export async function submitAppointment(
  payload: AppointmentPayload,
): Promise<SubmitAppointmentResult> {
  const response = await fetch(`${BACKEND_API_URL}/public/appointments`, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(payload),
    cache: "no-store",
  });

  if (response.ok) {
    return { ok: true };
  }

  if (response.status === 422) {
    const body = (await response.json()) as { message?: string };
    return { ok: false, message: body.message ?? "Ma'lumotlarni tekshiring." };
  }

  return { ok: false, message: "Server bilan bog'lanib bo'lmadi. Birozdan so'ng qayta urinib ko'ring." };
}
