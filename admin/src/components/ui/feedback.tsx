import { Loader2 } from 'lucide-react'
import type { ReactNode } from 'react'

import { ApiError } from '@/lib/api/client'
import { cn } from '@/lib/utils'

export function Spinner({ className }: { className?: string }) {
  return <Loader2 className={cn('h-4 w-4 animate-spin', className)} aria-hidden />
}

export function LoadingBlock({ label }: { label: string }) {
  return (
    <div className="flex items-center justify-center gap-2 py-12 text-neutral-500">
      <Spinner />
      <span>{label}</span>
    </div>
  )
}

/**
 * Xato bloki. Matn backenddan keladi — u allaqachon kerakli tilda va
 * "nima qilish kerak" ohangida yozilgan (PROJECT.md §10).
 */
export function ErrorBlock({ error, onRetry, retryLabel }: {
  error: unknown
  onRetry?: () => void
  retryLabel?: string
}) {
  const message =
    error instanceof ApiError
      ? error.message
      : error instanceof Error
        ? error.message
        : String(error)

  return (
    <div className="rounded-xl bg-red-50 p-4 text-sm text-red-800 ring-1 ring-red-200">
      <p>{message}</p>
      {onRetry !== undefined && (
        <button
          type="button"
          onClick={onRetry}
          className="mt-2 font-medium text-red-900 underline underline-offset-2"
        >
          {retryLabel ?? 'Qayta urinish'}
        </button>
      )}
    </div>
  )
}

export function Badge({
  tone,
  children,
}: {
  tone: 'green' | 'amber' | 'red' | 'neutral'
  children: ReactNode
}) {
  const tones = {
    green: 'bg-green-50 text-green-700 ring-green-200',
    amber: 'bg-amber-50 text-amber-800 ring-amber-200',
    red: 'bg-red-50 text-red-700 ring-red-200',
    neutral: 'bg-neutral-100 text-neutral-700 ring-neutral-200',
  } as const

  return (
    <span
      className={cn(
        'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset',
        tones[tone],
      )}
    >
      {children}
    </span>
  )
}
