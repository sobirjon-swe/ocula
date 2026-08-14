import type { ReactNode, ThHTMLAttributes } from 'react'

import { cn } from '@/lib/utils'

export function Table({ children }: { children: ReactNode }) {
  return (
    <div className="overflow-x-auto rounded-xl bg-white ring-1 ring-neutral-200">
      <table className="w-full border-collapse text-sm">{children}</table>
    </div>
  )
}

export function Th({ className, ...props }: ThHTMLAttributes<HTMLTableCellElement>) {
  return (
    <th
      scope="col"
      className={cn(
        'border-b border-neutral-200 bg-neutral-50 px-4 py-2.5 text-left',
        'text-xs font-semibold tracking-wide text-neutral-500 uppercase whitespace-nowrap',
        className,
      )}
      {...props}
    />
  )
}

export function Td({ className, children }: { className?: string; children: ReactNode }) {
  return (
    <td className={cn('border-b border-neutral-100 px-4 py-2.5 align-middle', className)}>
      {children}
    </td>
  )
}

export function EmptyRow({ colSpan, children }: { colSpan: number; children: ReactNode }) {
  return (
    <tr>
      <td colSpan={colSpan} className="px-4 py-12 text-center text-neutral-500">
        {children}
      </td>
    </tr>
  )
}
