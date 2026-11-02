import { useEffect, useId, useRef, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api, ApiError, idempotencyKey } from '@/lib/api/client'
import { useSession } from '@/lib/session'
import { money } from './money'
import { useLabel, useWords } from './copy'
export type Row = { [key: string]: unknown; id: number }
export type Field = {
    name: string
    type?: string
    options?: string[]
    lookup?: string
    optional?: boolean
    value?: string
}
export const f = (name: string, type = 'text', optional = false): Field => ({
    name,
    type,
    optional,
})
export const select = (
    name: string,
    options: string[],
    optional = false,
): Field => ({ name, options, optional })
export const lookup = (
    name: string,
    path: string,
    optional = false,
): Field => ({ name, lookup: path, optional })
export function rows(value: unknown): Row[] {
    if (Array.isArray(value)) return value as Row[]
    if (value && typeof value === 'object')
        return rows((value as Record<string, unknown>).data)
    return []
}
export function textValue(value: unknown): string {
    if (value === null || value === undefined) return '—'
    if (typeof value === 'object') {
        const r = value as Record<string, unknown>
        return String(r.name ?? r.number ?? r.sku ?? r.id ?? '—')
    }
    return String(value)
}
export function useList(
    path: string,
    params: Record<string, unknown> = {},
    enabled = true,
) {
    return useQuery({
        queryKey: ['workspace', path, params],
        queryFn: () => api.get<unknown>(path, { params }),
        enabled,
        refetchInterval: /^\/(visits|prescriptions|work-orders)/.test(path)
            ? 15000
            : false,
    })
}
export function ErrorNotice({ error }: { error: unknown }) {
    return error ? (
        <div
            role="alert"
            className="rounded-lg bg-red-50 p-3 text-sm text-red-800"
        >
            {error instanceof Error ? error.message : String(error)}
        </div>
    ) : null
}
export function FieldInput({
    field,
    value,
    onChange,
    error,
}: {
    field: Field
    value: string
    onChange: (v: string) => void
    error?: string
}) {
    const inputId = useId(),
        [photoError, setPhotoError] = useState('')
    const label = useLabel(),
        w = useWords()
    const user = useSession((s) => s.user),
        permissions = useSession((s) => s.permissions),
        [search, setSearch] = useState('')
    const localBranch =
        field.lookup === '/branches' &&
        !permissions.includes('core.branch.view_any')
    const localUser =
        field.lookup === '/users' && !permissions.includes('core.user.view_any')
    const data = useList(
        field.lookup ?? '',
        {
            per_page: 100,
            ...(search
                ? field.lookup === '/catalog-variants' ||
                  field.lookup === '/products'
                    ? { search }
                    : { filter: { name: search } }
                : {}),
        },
        !!field.lookup && !localBranch && !localUser,
    )
    const branchData = useList(
        '/branches',
        { per_page: 100 },
        field.lookup === '/locations' &&
            permissions.includes('core.branch.view_any'),
    )
    const options =
        localBranch && user?.branch_id
            ? [
                  (user.branch ?? {
                      id: user.branch_id,
                      name:
                          w('Filial', 'Филиал', 'Branch') +
                          ' #' +
                          user.branch_id,
                  }) as unknown as Row,
              ]
            : localUser && user
              ? [user as unknown as Row]
              : rows(data.data)
    const className =
        'w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm focus:outline-2 focus:outline-brand-600'
    return (
        <label htmlFor={inputId} className="grid gap-1.5 text-sm font-medium">
            {label(field.name)}
            {!field.optional && ' *'}
            {field.lookup && !localBranch && !localUser && (
                <input
                    aria-label={
                        w('Qidirish', 'Поиск', 'Search') +
                        ' · ' +
                        label(field.name)
                    }
                    className={className}
                    type="search"
                    placeholder={w('Qidirish…', 'Поиск…', 'Search…')}
                    value={search}
                    onChange={(e) => setSearch(e.target.value)}
                />
            )}
            {field.type === 'photo' ? (
                <div className="space-y-2">
                    <input
                        id={inputId}
                        className="w-full text-sm"
                        type="file"
                        accept="image/jpeg,image/png,image/webp"
                        capture="environment"
                        onChange={(e) => {
                            const file = e.target.files?.[0]
                            if (!file) return
                            if (file.size > 4 * 1024 * 1024) {
                                setPhotoError(
                                    w(
                                        'Rasm 4 MB dan kichik bo‘lishi kerak.',
                                        'Фото должно быть меньше 4 МБ.',
                                        'Choose an image smaller than 4 MB.',
                                    ),
                                )
                                return
                            }
                            const reader = new FileReader()
                            reader.onload = () => {
                                onChange(String(reader.result))
                                setPhotoError('')
                            }
                            reader.onerror = () =>
                                setPhotoError(
                                    w(
                                        'Rasm o‘qilmadi. Boshqa rasmni tanlang.',
                                        'Не удалось прочитать фото. Выберите другое.',
                                        'Could not read the image. Choose another one.',
                                    ),
                                )
                            reader.readAsDataURL(file)
                            e.target.value = ''
                        }}
                    />
                    {value && (
                        <img
                            className="max-h-40 rounded-lg object-contain"
                            src={value}
                            alt={label(field.name)}
                        />
                    )}
                    <ErrorNotice error={photoError} />
                </div>
            ) : field.lookup || field.options ? (
                <select
                    id={inputId}
                    className={className}
                    value={value}
                    onChange={(e) => onChange(e.target.value)}
                    required={!field.optional}
                >
                    <option value="">
                        {w('Tanlang', 'Выберите', 'Choose')}
                    </option>
                    {field.options?.map((v) => (
                        <option key={v} value={v}>
                            {label(v)}
                        </option>
                    ))}
                    {options.map((r) => (
                        <option key={r.id} value={r.id}>
                            {field.lookup === '/catalog-variants'
                                ? textValue((r.product as Row)?.name) +
                                  ' · ' +
                                  textValue(r.sku) +
                                  ' · ' +
                                  textValue(r.optical_label)
                                : textValue(r.name ?? r.number ?? r.sku)}
                            {field.lookup === '/locations'
                                ? ' · ' +
                                  textValue(
                                      rows(branchData.data).find(
                                          (b) => b.id === r.branch_id,
                                      )?.name ??
                                          (r.branch_id === user?.branch_id
                                              ? user?.branch?.name
                                              : r.branch_id),
                                  )
                                : ''}{' '}
                            · #{r.id}
                        </option>
                    ))}
                </select>
            ) : field.type === 'textarea' ? (
                <textarea
                    id={inputId}
                    className={className}
                    value={value}
                    onChange={(e) => onChange(e.target.value)}
                    required={!field.optional}
                    rows={3}
                />
            ) : (
                <input
                    id={inputId}
                    className={className}
                    type={
                        field.type === 'decimal'
                            ? 'number'
                            : (field.type ?? 'text')
                    }
                    step={
                        field.type === 'decimal'
                            ? '0.01'
                            : field.type === 'number'
                              ? '1'
                              : undefined
                    }
                    value={value}
                    onChange={(e) => onChange(e.target.value)}
                    required={!field.optional}
                    autoComplete={
                        field.type === 'password' ? 'new-password' : undefined
                    }
                />
            )}{' '}
            {error && <span className="text-xs text-red-700">{error}</span>}
            {data.isError && <ErrorNotice error={data.error} />}
        </label>
    )
}
export function DomainForm({
    fields,
    onSave,
    initial = {},
    submitLabel,
    children,
    resetKey,
}: {
    fields: Field[]
    onSave: (body: Record<string, unknown>) => Promise<unknown>
    initial?: Record<string, unknown>
    submitLabel?: string
    children?: React.ReactNode
    resetKey?: string
}) {
    const w = useWords(),
        branch = useSession((s) => s.user?.branch_id)
    const [values, setValues] = useState<Record<string, string>>(() =>
        Object.fromEntries(
            fields.map((field) => [
                field.name,
                String(
                    initial[field.name] ??
                        field.value ??
                        (field.name === 'branch_id'
                            ? (branch ?? '')
                            : field.type === 'date'
                              ? new Date().toISOString().slice(0, 10)
                              : ''),
                ),
            ]),
        ),
    )
    const mutation = useMutation({
        mutationFn: async () =>
            onSave(
                Object.fromEntries(
                    fields
                        .filter((field) => values[field.name] !== '')
                        .map((field) => [
                            field.name,
                            field.type === 'number' || field.lookup
                                ? Number(values[field.name])
                                : field.type === 'checkbox' ||
                                    ['approved', 'is_active'].includes(
                                        field.name,
                                    )
                                  ? values[field.name] === 'true'
                                  : values[field.name],
                        ]),
                ),
            ),
    })
    useEffect(() => {
        mutation.reset()
    }, [resetKey, mutation.reset])
    return (
        <form
            className="space-y-4"
            onSubmit={(e) => {
                e.preventDefault()
                mutation.mutate()
            }}
        >
            <fieldset disabled={mutation.isPending} className="space-y-4">
                <div className="grid gap-4 sm:grid-cols-2">
                    {fields.map((field) => (
                        <FieldInput
                            key={field.name}
                            field={field}
                            value={values[field.name] ?? ''}
                            onChange={(value) => {
                                mutation.reset()
                                setValues((old) => ({
                                    ...old,
                                    [field.name]: value,
                                }))
                            }}
                            error={
                                mutation.error instanceof ApiError
                                    ? mutation.error.fieldError(field.name)
                                    : undefined
                            }
                        />
                    ))}
                </div>
                {children}
            </fieldset>
            <ErrorNotice error={mutation.error} />
            <button
                disabled={mutation.isPending || mutation.isSuccess}
                className="action-primary"
                type="submit"
            >
                {mutation.isPending
                    ? w('Saqlanmoqda…', 'Сохранение…', 'Saving…')
                    : (submitLabel ?? w('Saqlash', 'Сохранить', 'Save'))}
            </button>
            {mutation.isSuccess && (
                <p role="status" className="text-sm text-green-700">
                    {w('Saqlandi', 'Сохранено', 'Saved')}
                </p>
            )}
        </form>
    )
}
export function useWrite() {
    const qc = useQueryClient(),
        keys = useRef(new Map<string, string>())
    return async (
        path: string,
        body: unknown = {},
        method: 'post' | 'put' = 'post',
    ) => {
        const fingerprint = path + JSON.stringify(body)
        const key = keys.current.get(fingerprint) ?? idempotencyKey()
        keys.current.set(fingerprint, key)
        const result = await api[method]<unknown>(path, {
            body,
            idempotencyKey: key,
        })
        keys.current.delete(fingerprint)
        await qc.invalidateQueries({ queryKey: ['workspace'] })
        return result
    }
}
const monetaryColumns = new Set([
    'price',
    'amount',
    'total',
    'subtotal',
    'paid',
    'debt',
    'debt_balance',
    'remaining',
    'refund',
    'cost_price',
    'unit_cost',
    'opening_cash',
    'expected_cash',
    'actual_cash',
    'difference',
    'target_amount',
    'actual_amount',
    'base_amount',
    'balance',
    'net',
    'signed_amount',
    'cash_today',
    'cost_loss',
    'remaining',
])
export function displayValue(
    key: string,
    value: unknown,
    label: (key: string) => string,
): string {
    if (value == null) return '—'
    if (monetaryColumns.has(key)) return money(value)
    if (typeof value === 'boolean') return label(String(value))
    if (
        [
            'status',
            'payment_status',
            'type',
            'method',
            'reason',
            'priority',
            'direction',
            'category',
            'channel',
            'response',
            'base',
        ].includes(key) &&
        typeof value === 'string'
    )
        return label(value)
    if (
        (key.endsWith('_at') ||
            [
                'date',
                'due_date',
                'valid_from',
                'valid_to',
                'birth_date',
            ].includes(key)) &&
        value
    ) {
        const date = new Date(String(value))
        if (!Number.isNaN(date.getTime()))
            return date.toLocaleString('ru-RU', {
                timeZone: 'Asia/Tashkent',
                dateStyle: 'short',
                ...(key.endsWith('_at') ? { timeStyle: 'short' as const } : {}),
            })
    }
    return textValue(value)
}
export function DataTable({
    data,
    columns,
    onSelect,
}: {
    data: Row[]
    columns: string[]
    onSelect?: (r: Row) => void
}) {
    const label = useLabel(),
        w = useWords()
    return (
        <div className="relative overflow-x-auto rounded-lg border border-neutral-200">
            <table className="w-full text-left text-sm">
                <thead className="bg-neutral-50">
                    <tr>
                        {columns.map((c) => (
                            <th
                                key={c}
                                className="whitespace-nowrap px-4 py-3 font-medium"
                            >
                                {label(c)}
                            </th>
                        ))}
                        {onSelect && (
                            <th className="px-4 py-3">
                                <span className="sr-only">
                                    {w('Ochish', 'Открыть', 'Open')}
                                </span>
                            </th>
                        )}
                    </tr>
                </thead>
                <tbody>
                    {data.length === 0 && (
                        <tr>
                            <td
                                colSpan={columns.length + 1}
                                className="px-4 py-10 text-center text-neutral-500"
                            >
                                {w(
                                    'Yozuvlar topilmadi',
                                    'Записей нет',
                                    'No records found',
                                )}
                            </td>
                        </tr>
                    )}
                    {data.map((r, i) => (
                        <tr
                            key={r.id ?? i}
                            className="border-t border-neutral-100 hover:bg-neutral-50"
                        >
                            {columns.map((c) => (
                                <td
                                    key={c}
                                    className="whitespace-nowrap px-4 py-3 tabular-nums"
                                >
                                    {displayValue(c, r[c], label)}
                                </td>
                            ))}
                            {onSelect && (
                                <td className="px-4 py-3">
                                    <button
                                        className="text-brand-700 underline underline-offset-4"
                                        onClick={() => onSelect(r)}
                                    >
                                        {w('Ochish', 'Открыть', 'Open')}
                                    </button>
                                </td>
                            )}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    )
}
export function Skeleton() {
    return (
        <div
            role="status"
            aria-label="Loading"
            className="space-y-3 rounded-xl border border-neutral-200 bg-white p-5 motion-safe:animate-pulse"
        >
            <div className="h-5 w-2/5 rounded bg-neutral-200" />
            <div className="h-10 rounded bg-neutral-100" />
            <div className="h-10 rounded bg-neutral-100" />
            <div className="h-10 rounded bg-neutral-100" />
        </div>
    )
}
