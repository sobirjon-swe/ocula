import { clsx } from 'clsx'
import type { ClassValue } from 'clsx'
import { twMerge } from 'tailwind-merge'

/** Tailwind sinflarini xavfsiz birlashtirish (shadcn/ui konventsiyasi). */
export function cn(...inputs: ClassValue[]): string {
  return twMerge(clsx(inputs))
}

/**
 * Pul qiymatini ko'rsatish — `"1250000.00"` → `"1 250 000 so'm"`.
 *
 * Backend `CustomerOrderResource`/`debt` javobida `_formatted` varianti
 * yo'q (Finance/Payroll resurslaridan farqli) — shuning uchun bu yerda
 * qilinadi. Faqat ko'rinish, hisob-kitob emas (u backendda).
 */
export function formatMoney(value: string): string {
  const amount = Number.parseFloat(value)

  if (Number.isNaN(amount)) return value

  return `${new Intl.NumberFormat('ru-RU').format(amount)} so'm`
}

export function formatDate(value: string | null): string {
  if (value === null) return '—'

  return new Intl.DateTimeFormat('ru-RU', { day: '2-digit', month: '2-digit', year: 'numeric' }).format(
    new Date(value),
  )
}
