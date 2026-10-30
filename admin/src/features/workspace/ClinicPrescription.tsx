import { useState } from 'react'
import { useWords } from './copy'
import { DomainForm, f, rows, useList, useWrite, type Row } from './shared'
function flatten(row: Row): Record<string, string> {
    const result: Record<string, string> = {}
    for (const eye of ['od', 'os'])
        for (const key of ['sph', 'cyl', 'axis', 'add']) {
            const v = (row[eye] as Record<string, unknown>)?.[key]
            result[eye + '_' + key] = v == null ? '' : String(v)
        }
    for (const key of ['pd', 'pd_near', 'prism', 'notes'])
        result[key] = row[key] == null ? '' : String(row[key])
    return result
}
export function ClinicPrescription({
    visit,
    existing,
}: {
    visit: Row
    existing?: Row
}) {
    const w = useWords(),
        write = useWrite(),
        [values, setValues] = useState<Record<string, string>>(() =>
            existing ? flatten(existing) : {},
        ),
        [open, setOpen] = useState(false),
        [copyVersion, setCopyVersion] = useState(0)
    const history = useList(
            '/customers/' + visit.customer_id + '/prescriptions',
            {},
            open,
        ),
        last = rows(history.data)[0],
        previous = last ? flatten(last) : {}
    const warning = ['od_sph', 'os_sph', 'od_cyl', 'os_cyl'].some(
        (key) =>
            values[key] !== '' &&
            values[key] !== undefined &&
            previous[key] !== '' &&
            previous[key] !== undefined &&
            Math.abs(Number(values[key]) - Number(previous[key])) >= 1.5,
    )
    const diopters = Array.from({ length: 241 }, (_, i) =>
        ((i - 120) / 4).toFixed(2),
    )
    return (
        <details
            className="w-full"
            onToggle={(e) => setOpen(e.currentTarget.open)}
        >
            <summary className="cursor-pointer py-2 font-medium">
                {w(
                    'Retsept yozish · OD / OS',
                    'Выписать рецепт · OD / OS',
                    'Write prescription · OD / OS',
                )}
            </summary>
            <div className="my-4 space-y-4">
                {last && (
                    <button
                        className="action-secondary"
                        onClick={() => {
                            setValues(flatten(last))
                            setCopyVersion((v) => v + 1)
                        }}
                    >
                        {w(
                            'Oldingi retseptdan nusxa',
                            'Копировать прошлый рецепт',
                            'Copy previous prescription',
                        )}
                    </button>
                )}
                <div className="grid gap-5 sm:grid-cols-2">
                    {['od', 'os'].map((eye) => (
                        <fieldset
                            key={eye}
                            className="rounded-xl border border-neutral-200 p-4"
                        >
                            <legend className="px-2 text-lg font-semibold">
                                {eye.toUpperCase()} ·{' '}
                                {eye === 'od'
                                    ? w('O‘ng', 'Правый', 'Right')
                                    : w('Chap', 'Левый', 'Left')}
                            </legend>
                            <div className="grid grid-cols-2 gap-4">
                                {['sph', 'cyl', 'axis', 'add'].map((key) => (
                                    <label
                                        key={key}
                                        className="grid gap-2 text-sm font-medium"
                                    >
                                        {key.toUpperCase()}
                                        {key === 'axis' ? (
                                            <input
                                                aria-label={
                                                    eye.toUpperCase() + ' AXIS'
                                                }
                                                className="min-h-12 w-full rounded-lg border border-neutral-300 p-2"
                                                type="number"
                                                step="1"
                                                min="0"
                                                max="180"
                                                value={
                                                    values[eye + '_' + key] ??
                                                    ''
                                                }
                                                onChange={(e) =>
                                                    setValues((v) => ({
                                                        ...v,
                                                        [eye + '_' + key]:
                                                            e.target.value,
                                                    }))
                                                }
                                            />
                                        ) : (
                                            <select
                                                aria-label={
                                                    eye.toUpperCase() +
                                                    ' ' +
                                                    key.toUpperCase()
                                                }
                                                className="min-h-12 w-full rounded-lg border border-neutral-300 bg-white p-2"
                                                value={
                                                    values[eye + '_' + key] ??
                                                    ''
                                                }
                                                onChange={(e) =>
                                                    setValues((v) => ({
                                                        ...v,
                                                        [eye + '_' + key]:
                                                            e.target.value,
                                                    }))
                                                }
                                            >
                                                <option value="">—</option>
                                                {diopters.map((n) => (
                                                    <option key={n} value={n}>
                                                        {Number(n) > 0
                                                            ? '+'
                                                            : ''}
                                                        {n}
                                                    </option>
                                                ))}
                                            </select>
                                        )}
                                    </label>
                                ))}
                            </div>
                        </fieldset>
                    ))}
                </div>
                {warning && (
                    <p
                        role="alert"
                        className="rounded-lg bg-amber-50 p-3 text-sm text-amber-900"
                    >
                        {w(
                            'Oldingi retseptdan 1.5 yoki ko‘proq farq bor. SPH/CYL qiymatlarini tekshiring.',
                            'Изменение от прошлого рецепта на 1.5 или больше. Перепроверьте SPH/CYL.',
                            'The prescription changed by 1.5 or more. Double-check SPH/CYL.',
                        )}
                    </p>
                )}
                <DomainForm
                    fields={[
                        f('pd', 'decimal', true),
                        f('pd_near', 'decimal', true),
                        f('prism', 'text', true),
                        f('notes', 'textarea', true),
                    ]}
                    key={copyVersion}
                    resetKey={JSON.stringify(values)}
                    initial={values}
                    onSave={(body) => {
                        for (const eye of ['od', 'os']) {
                            const axis = values[eye + '_axis']
                            if (
                                axis !== undefined &&
                                axis !== '' &&
                                (!Number.isInteger(Number(axis)) ||
                                    Number(axis) < 0 ||
                                    Number(axis) > 180)
                            )
                                throw new Error(
                                    w(
                                        'AXIS 0 dan 180 gacha bo‘lishi kerak.',
                                        'AXIS должен быть от 0 до 180.',
                                        'AXIS must be between 0 and 180.',
                                    ),
                                )
                        }
                        return write(
                            existing
                                ? '/prescriptions/' + existing.id
                                : '/prescriptions',
                            {
                                ...Object.fromEntries(
                                    Object.entries(values).filter(
                                        ([, v]) => v !== '',
                                    ),
                                ),
                                ...body,
                                customer_id: visit.customer_id,
                                branch_id: visit.branch_id,
                                visit_id: visit.id,
                            },
                            existing ? 'put' : 'post',
                        )
                    }}
                />
            </div>
        </details>
    )
}
