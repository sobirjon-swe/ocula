import { NextResponse } from "next/server";
import { submitAppointment } from "@/lib/api";
import type { AppointmentPayload } from "@/lib/types";

/**
 * Booking formasi shu yerga POST qiladi (brauzerdan, o'z origin'iga —
 * CORS kerak emas), bu esa backendga server tomonda uzatadi
 * (BOSQICH-11.md).
 */
export async function POST(request: Request) {
  const body = (await request.json()) as Partial<AppointmentPayload>;

  if (!body.branch_id || !body.name || !body.phone) {
    return NextResponse.json(
      { ok: false, message: "Filial, ism va telefon raqami majburiy." },
      { status: 422 },
    );
  }

  const result = await submitAppointment({
    branch_id: body.branch_id,
    name: body.name,
    phone: body.phone,
    preferred_date: body.preferred_date,
    note: body.note,
  });

  return NextResponse.json(result, { status: result.ok ? 201 : 422 });
}
