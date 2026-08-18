import type { InputHTMLAttributes, ReactNode, SelectHTMLAttributes } from 'react'
import { useId } from 'react'

import { cn } from '@/lib/utils'

const FIELD = cn(
  'block w-full rounded-xl bg-white px-3 py-2.5 text-base text-neutral-900',
  'ring-1 ring-neutral-300 ring-inset placeholder:text-neutral-400',
  'focus:ring-brand-500 focus:ring-2 focus:outline-none',
  'disabled:bg-neutral-100 disabled:text-neutral-500',
)

type FieldProps = {
  label?: string
  error?: string
  children: (id: string) => ReactNode
}

function Field({ label, error, children }: FieldProps) {
  const id = useId()

  return (
    <div className="space-y-1.5">
      {label !== undefined && (
        <label htmlFor={id} className="block text-sm font-medium text-neutral-700">
          {label}
        </label>
      )}
      {children(id)}
      {error !== undefined && <p className="text-sm text-red-600">{error}</p>}
    </div>
  )
}

type InputProps = InputHTMLAttributes<HTMLInputElement> & {
  label?: string
  error?: string
}

export function Input({ label, error, className, ...props }: InputProps) {
  return (
    <Field label={label} error={error}>
      {(id) => (
        <input
          id={id}
          aria-invalid={error !== undefined}
          className={cn(FIELD, error !== undefined && 'ring-red-400', className)}
          {...props}
        />
      )}
    </Field>
  )
}

type SelectProps = SelectHTMLAttributes<HTMLSelectElement> & {
  label?: string
  error?: string
}

export function Select({ label, error, className, children, ...props }: SelectProps) {
  return (
    <Field label={label} error={error}>
      {(id) => (
        <select id={id} className={cn(FIELD, 'pr-8', className)} {...props}>
          {children}
        </select>
      )}
    </Field>
  )
}
