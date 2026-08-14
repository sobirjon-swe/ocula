import { clsx } from 'clsx'
import type { ClassValue } from 'clsx'
import { twMerge } from 'tailwind-merge'

/** Tailwind sinflarini xavfsiz birlashtirish (shadcn/ui konventsiyasi). */
export function cn(...inputs: ClassValue[]): string {
  return twMerge(clsx(inputs))
}

/** Ombor miqdori butun son — 1000 → "1 000". */
export function formatQuantity(value: number): string {
  return new Intl.NumberFormat('ru-RU').format(value)
}
