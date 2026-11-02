import { useEffect, useState } from 'react'
import { useParams } from 'react-router-dom'
import { useSession } from '@/lib/session'
import { useWords, useLabel } from './copy'
import { resources, type Resource } from './resources'
import {
    Skeleton,
    displayValue,
    DataTable,
    DomainForm,
    ErrorNotice,
    FieldInput,
    f,
    lookup,
    rows,
    select,
    textValue,
    useList,
    useWrite,
    type Field,
    type Row,
} from './shared'
import { AttachmentImage } from './AttachmentImage'
import { ReceiptButton } from './ReceiptButton'
import { DeviceReceipt } from './QuickProduct'
import { ClinicPrescription } from './ClinicPrescription'

export function LineEditor({
    fields,
    value,
    onChange,
}: {
    fields: Field[]
    value: Record<string, string>[]
    onChange: (v: Record<string, string>[]) => void
}) {
    const w = useWords()
    return (
        <fieldset className="space-y-3">
            <legend className="mb-3 font-medium">
                {w('Tovarlar', 'Товары', 'Items')}
            </legend>
            {value.map((line, i) => (
                <div
                    key={i}
                    className="grid items-end gap-3 rounded-lg bg-neutral-50 p-3 sm:grid-cols-3"
                >
                    {fields.map((field) => (
                        <FieldInput
                            key={field.name}
                            field={field}
                            value={line[field.name] ?? ''}
                            onChange={(v) =>
                                onChange(
                                    value.map((l, j) =>
                                        i === j ? { ...l, [field.name]: v } : l,
                                    ),
                                )
                            }
                        />
                    ))}
                    <button
                        type="button"
                        className="text-left text-sm text-red-700"
                        onClick={() =>
                            onChange(value.filter((_, j) => i !== j))
                        }
                    >
                        {w('O‘chirish', 'Удалить', 'Remove')}
                    </button>
                </div>
            ))}
            <button
                className="action-secondary"
                type="button"
                onClick={() => onChange([...value, {}])}
            >
                {w('Tovar qo‘shish', 'Добавить товар', 'Add item')}
            </button>
        </fieldset>
    )
}
export function ResourceCreate({
    resource,
    onDone,
}: {
    resource: Resource
    onDone: () => void
}) {
    const write = useWrite(),
        [items, setItems] = useState<Record<string, string>[]>([{}]),
        [device, setDevice] = useState<Row | null>(null),
        [staffRoles, setStaffRoles] = useState<string[]>([])
    if (device) return <DeviceReceipt device={device} onDone={onDone} />
    return (
        <DomainForm
            fields={[
                ...(resource.fields ?? []),
                ...(['services', 'categories', 'expense-categories'].includes(
                    resource.path,
                )
                    ? [f('name_ru', 'text', true), f('name_en', 'text', true)]
                    : []),
            ]}
            onSave={async (body) => {
                const result = await write('/' + resource.path, {
                    ...body,
                    ...(resource.path === 'users' ? { roles: staffRoles } : {}),
                    ...([
                        'services',
                        'categories',
                        'expense-categories',
                    ].includes(resource.path)
                        ? {
                              name_translations: {
                                  'uz-latn': body.name,
                                  ru: body.name_ru,
                                  en: body.name_en,
                              },
                          }
                        : {}),
                    ...(resource.items
                        ? {
                              items: items.map((item) =>
                                  Object.fromEntries(
                                      Object.entries(item).map(([key, v]) => [
                                          key,
                                          key.endsWith('_id') ||
                                          key === 'quantity'
                                              ? Number(v)
                                              : v,
                                      ]),
                                  ),
                              ),
                          }
                        : {}),
                })
                if (resource.path === 'devices')
                    setDevice((result as { data: Row }).data)
                else onDone()
            }}
        >
            {resource.path === 'users' && (
                <RoleChoices value={staffRoles} onChange={setStaffRoles} />
            )}
            {resource.items && (
                <LineEditor
                    fields={resource.items}
                    value={items}
                    onChange={setItems}
                />
            )}
        </DomainForm>
    )
}
function Action({
    label,
    fields = [],
    path,
    method = 'post',
    initial = {},
}: {
    label: string
    fields?: Field[]
    path: string
    method?: 'post' | 'put'
    initial?: Record<string, unknown>
}) {
    const write = useWrite(),
        [open, setOpen] = useState(false)
    return (
        <div>
            <button
                className="action-secondary"
                onClick={() => setOpen(!open)}
                aria-expanded={open}
            >
                {label}
            </button>
            {open && (
                <div className="my-3 rounded-lg border border-neutral-200 p-4">
                    <DomainForm
                        fields={fields}
                        initial={initial}
                        submitLabel={label}
                        onSave={(body) => write(path, body, method)}
                    />
                </div>
            )}
        </div>
    )
}
export function ResourceDetails({
    resource,
    row,
    onClose,
}: {
    resource: Resource
    row: Row
    onClose: () => void
}) {
    const w = useWords(),
        label = useLabel(),
        permissions = useSession((s) => s.permissions),
        write = useWrite()
    const can = (p: string) => permissions.includes(p)
    const path = '/' + resource.path + '/' + row.id
    const detail = useList(
        path,
        {},
        ![
            'audit-log',
            'cash/movements',
            'stock/movements',
            'bonus-entries',
            'branch-plans',
            'bonus-rules',
            'master-stock',
            'appointment-requests',
            'supplier-returns',
            'payments',
            'supplier-transactions',
        ].includes(resource.path),
    )
    const r = (detail.data as { data?: Row })?.data ?? row,
        items = rows(r.items)
    const detailKeys = new Set([
        ...resource.columns,
        ...(resource.fields ?? []).map((field) => field.name),
        'notes',
        'note',
        'due_date',
        'delivery_type',
        'prescription_id',
        'paid',
        'debt',
        'subtotal',
        'discount',
        'rounding',
        'total',
        'opening_cash',
        'expected_cash',
        'actual_cash',
        'difference',
        'debt_approved_by',
        'debt_approval_reason',
        'debt_approved_amount',
        'created_at',
        'delivered_at',
        'closed_at',
        'approved_by',
        'valid_until',
        'is_active',
        'rework_count',
    ])
    return (
        <section
            className={
                'print-target space-y-5 rounded-xl border border-neutral-200 bg-white p-5 ' +
                (resource.path === 'work-orders'
                    ? '[&_button]:min-h-[60px]'
                    : '')
            }
            aria-label={w('Tafsilotlar', 'Подробности', 'Details')}
        >
            <div className="flex items-center justify-between gap-3">
                <h2 className="text-lg font-semibold">
                    {textValue(r.number ?? r.name ?? r.id)}
                </h2>
                <button className="action-secondary" onClick={onClose}>
                    {w('Yopish', 'Закрыть', 'Close')}
                </button>
            </div>
            <ErrorNotice error={detail.error} />
            <dl className="grid gap-x-8 gap-y-3 sm:grid-cols-3">
                {Object.entries(r)
                    .filter(
                        ([k, v]) =>
                            detailKeys.has(k) &&
                            v !== null &&
                            !Array.isArray(v) &&
                            typeof v !== 'object' &&
                            !['password', 'token'].includes(k),
                    )
                    .map(([k, v]) => (
                        <div key={k}>
                            <dt className="text-xs text-neutral-500">
                                {label(k)}
                            </dt>
                            <dd className="mt-1 break-words text-sm tabular-nums">
                                {displayValue(k, v, label)}
                            </dd>
                        </div>
                    ))}
            </dl>
            {resource.path === 'audit-log' && <AuditChanges row={r} />}
            {rows(r.payments).length > 0 && (
                <DataTable
                    data={rows(r.payments)}
                    columns={['id', 'amount', 'method', 'created_at']}
                />
            )}
            {rows(r.reminders).length > 0 && (
                <DataTable
                    data={rows(r.reminders)}
                    columns={['channel', 'response', 'note', 'sent_at']}
                />
            )}
            {items.length > 0 && (
                <DataTable
                    data={items}
                    columns={
                        resource.path === 'inventories'
                            ? [
                                  'variant',
                                  'expected_qty',
                                  'actual_qty',
                                  'unit_cost',
                              ]
                            : [
                                  'id',
                                  'variant',
                                  'name',
                                  'quantity',
                                  'price',
                                  'total',
                                  'status',
                              ]
                    }
                />
            )}
            {resource.edit &&
                can(
                    resource.path === 'products'
                        ? 'catalog.product.update'
                        : resource.path === 'customers'
                          ? 'sales.customer.update'
                          : resource.path === 'suppliers'
                            ? 'finance.supplier.manage'
                            : resource.path === 'users'
                              ? 'core.user.update'
                              : resource.path === 'branches'
                                ? 'core.branch.update'
                                : (resource.createPermission ?? ''),
                ) && (
                    <details>
                        <summary className="cursor-pointer py-2 font-medium">
                            {w('Tahrirlash', 'Редактировать', 'Edit')}
                        </summary>
                        <DomainForm
                            fields={[
                                ...(resource.fields ?? []).filter(
                                    (f) =>
                                        f.name !== 'password' &&
                                        !(
                                            resource.path === 'branches' &&
                                            f.name === 'code'
                                        ),
                                ),
                                select('is_active', ['true', 'false'], true),
                                ...(resource.path === 'users'
                                    ? [f('password', 'password', true)]
                                    : []),
                                ...([
                                    'services',
                                    'categories',
                                    'expense-categories',
                                ].includes(resource.path)
                                    ? [
                                          f('name_ru', 'text', true),
                                          f('name_en', 'text', true),
                                      ]
                                    : []),
                            ]}
                            initial={{
                                ...r,
                                name:['services','categories','expense-categories'].includes(resource.path)?((r.name_translations as Record<string,unknown>)?.['uz-latn']??r.name):r.name,
                                name_ru: (
                                    r.name_translations as Record<
                                        string,
                                        unknown
                                    >
                                )?.ru,
                                name_en: (
                                    r.name_translations as Record<
                                        string,
                                        unknown
                                    >
                                )?.en,
                            }}
                            onSave={(body) =>
                                write(
                                    path,
                                    {
                                        ...body,
                                        ...([
                                            'services',
                                            'categories',
                                            'expense-categories',
                                        ].includes(resource.path)
                                            ? {
                                                  name_translations: {
                                                      'uz-latn': body.name,
                                                      ru: body.name_ru,
                                                      en: body.name_en,
                                                  },
                                              }
                                            : {}),
                                    },
                                    'put',
                                )
                            }
                        />
                    </details>
                )}
            <div className="flex flex-wrap items-start gap-3">
                {resource.path === 'supplier-returns' &&
                    can('warehouse.defect.approve') &&
                    r.status === 'draft' && (
                        <Action
                            label={w(
                                'Ta’minotchiga qaytarishni tasdiqlash',
                                'Провести возврат поставщику',
                                'Post supplier return',
                            )}
                            path={path + '/post'}
                            fields={[lookup('purchase_id', '/purchases')]}
                        />
                    )}
                {resource.path === 'payments' &&
                    can('sales.payment.reverse') && (
                        <Action
                            label={w('Storno', 'Сторно', 'Reverse payment')}
                            path={path + '/reverse'}
                            fields={[f('reason', 'textarea')]}
                        />
                    )}
                {resource.path === 'prescriptions' && (
                    <>
                        <PrescriptionCard row={r} />
                        {can('clinic.prescription.update') && (
                            <ClinicPrescription
                                visit={{ ...r, id: Number(r.visit_id) }}
                                existing={r}
                            />
                        )}
                        {can('clinic.ticket.transfer_branch') && (
                            <Action
                                label={w(
                                    'Tiketni boshqa filialga',
                                    'Перенести тикет',
                                    'Transfer ticket',
                                )}
                                path={path + '/transfer-ticket'}
                                fields={[
                                    lookup('to_branch_id', '/branches'),
                                    f('reason', 'textarea'),
                                ]}
                            />
                        )}
                    </>
                )}
                {resource.path === 'stock/movements' &&
                    can('warehouse.movement.reverse') && (
                        <Action
                            label={w('Storno', 'Сторно', 'Reverse movement')}
                            path={path + '/reverse'}
                            fields={[f('reason', 'textarea')]}
                        />
                    )}
                {resource.path === 'defects' &&
                    can('warehouse.defect.approve') && (
                        <Action
                            label={w('Tasdiqlash', 'Одобрить', 'Approve')}
                            path={path + '/approve'}
                        />
                    )}
                {resource.path === 'orders' && (
                    <>
                        {can('sales.payment.create') && (
                            <Action
                                label={w(
                                    'To‘lov qabul qilish',
                                    'Принять оплату',
                                    'Take payment',
                                )}
                                path={path + '/payments'}
                                fields={[
                                    f('amount', 'decimal'),
                                    select('method', [
                                        'cash',
                                        'card',
                                        'transfer',
                                    ]),
                                ]}
                            />
                        )}{' '}
                        {can('sales.order.change_status') && (
                            <Action
                                label={w(
                                    'Holatni o‘zgartirish',
                                    'Изменить статус',
                                    'Change status',
                                )}
                                path={path + '/status'}
                                fields={[
                                    select(
                                        'status',
                                        Array.isArray(r.allowed_next_statuses)
                                            ? (r.allowed_next_statuses as string[])
                                            : [],
                                    ),
                                ]}
                            />
                        )}{' '}
                        {can('sales.order.deliver') && (
                            <Action
                                label={w(
                                    'Mijozga topshirish',
                                    'Выдать клиенту',
                                    'Hand over',
                                )}
                                path={path + '/deliver'}
                            />
                        )}{' '}
                        {can('finance.debt.approve') && (
                            <Action
                                label={w(
                                    'Qarzni tasdiqlash',
                                    'Одобрить долг',
                                    'Approve credit',
                                )}
                                path={path + '/approve-debt'}
                                fields={[f('reason', 'textarea')]}
                            />
                        )}{' '}
                        {can('sales.order.cancel') && (
                            <Action
                                label={w('Bekor qilish', 'Отменить', 'Cancel')}
                                path={path + '/cancel'}
                            />
                        )}
                        {can('sales.receipt.print') && (
                            <ReceiptButton orderId={r.id} />
                        )}
                        {can('sales.return.create') && <ReturnForm order={r} />}
                    </>
                )}
                {resource.path === 'shifts' &&
                    can('core.shift.close') &&
                    r.status === 'open' && (
                        <Action
                            label={w(
                                'Smenani yopish',
                                'Закрыть смену',
                                'Close shift',
                            )}
                            path={path + '/close'}
                            fields={[
                                f('actual_cash', 'decimal'),
                                f('note', 'textarea', true),
                            ]}
                        />
                    )}
                {resource.path === 'purchases' && r.status === 'draft' && (
                    <>
                        {can('warehouse.purchase.receive') && (
                            <Action
                                label={w(
                                    'Kirimni qabul qilish',
                                    'Принять закупку',
                                    'Receive purchase',
                                )}
                                path={path + '/receive'}
                            />
                        )}{' '}
                        {can('warehouse.purchase.cancel') && (
                            <Action
                                label={w('Bekor qilish', 'Отменить', 'Cancel')}
                                path={path + '/cancel'}
                            />
                        )}
                    </>
                )}
                {resource.path === 'transfers' && (
                    <>
                        {r.taxi_receipt_path != null && (
                            <AttachmentImage path={path + '/taxi-receipt'} />
                        )}
                        {r.status === 'draft' &&
                            can('warehouse.transfer.send') && (
                                <Action
                                    label={w('Jo‘natish', 'Отправить', 'Send')}
                                    path={path + '/send'}
                                    fields={[
                                        select('delivery_method', [
                                            'own_driver',
                                            'taxi',
                                            'by_hand',
                                        ]),
                                        lookup('carrier_id', '/users', true),
                                        f('taxi_cost', 'decimal', true),
                                        f('taxi_receipt_photo', 'photo', true),
                                    ]}
                                />
                            )}{' '}
                        {can('warehouse.transfer.receive') &&
                            ['sent', 'in_transit'].includes(
                                String(r.status),
                            ) && <TransferReceive row={r} />}{' '}
                        {can('warehouse.transfer.resolve_discrepancy') && (
                            <Action
                                label={w(
                                    'Tafovutni hal qilish',
                                    'Урегулировать расхождение',
                                    'Resolve discrepancy',
                                )}
                                path={path + '/resolve-discrepancy'}
                                fields={[f('resolution', 'textarea')]}
                            />
                        )}
                    </>
                )}
                {resource.path === 'stock-requests' &&
                    ['approve', 'reject', 'cancel', 'fulfill'].map(
                        (action) =>
                            can(
                                'warehouse.request.' +
                                    (action === 'reject'
                                        ? 'approve'
                                        : action === 'cancel'
                                          ? 'create'
                                          : action),
                            ) && (
                                <Action
                                    key={action}
                                    label={w(
                                        action === 'approve'
                                            ? 'Tasdiqlash'
                                            : action === 'reject'
                                              ? 'Rad etish'
                                              : action === 'cancel'
                                                ? 'Bekor qilish'
                                                : 'Transfer yaratish',
                                        action === 'approve'
                                            ? 'Одобрить'
                                            : action === 'reject'
                                              ? 'Отклонить'
                                              : action === 'cancel'
                                                ? 'Отменить'
                                                : 'Создать перемещение',
                                        action,
                                    )}
                                    path={path + '/' + action}
                                />
                            ),
                    )}
                {resource.path === 'inventories' && <InventoryCounts row={r} />}
                {resource.path === 'visits' && (
                    <>
                        {['start', 'finish', 'cancel', 'no-show'].map(
                            (action) =>
                                can(
                                    'clinic.visit.' +
                                        (action === 'no-show'
                                            ? 'cancel'
                                            : action),
                                ) && (
                                    <Action
                                        key={action}
                                        label={w(
                                            action === 'start'
                                                ? 'Qabulni boshlash'
                                                : action === 'finish'
                                                  ? 'Qabulni tugatish'
                                                  : action === 'no-show'
                                                    ? 'Kelmadi'
                                                    : 'Bekor qilish',
                                            action === 'start'
                                                ? 'Начать приём'
                                                : action === 'finish'
                                                  ? 'Завершить приём'
                                                  : action === 'no-show'
                                                    ? 'Не явился'
                                                    : 'Отменить',
                                            action,
                                        )}
                                        path={path + '/' + action}
                                    />
                                ),
                        )}
                        {can('clinic.prescription.create') && (
                            <ClinicPrescription visit={r} />
                        )}
                    </>
                )}
                {resource.path === 'appointment-requests' && (
                    <Action
                        label={w('Holat', 'Статус', 'Status')}
                        path={path + '/status'}
                        method="put"
                        fields={[
                            select('status', [
                                'contacted',
                                'booked',
                                'declined',
                            ]),
                        ]}
                    />
                )}
                {resource.path === 'work-orders' && (
                    <>
                        {can('workshop.work_order.assign') && (
                            <Action
                                label={w(
                                    'Ustaga biriktirish',
                                    'Назначить мастера',
                                    'Assign master',
                                )}
                                path={path + '/master'}
                                method="put"
                                fields={[lookup('master_id', '/users')]}
                            />
                        )}{' '}
                        {can('workshop.work_order.set_priority') && (
                            <Action
                                label={w('Muhimlik', 'Приоритет', 'Priority')}
                                path={path + '/priority'}
                                method="put"
                                fields={[
                                    select('priority', [
                                        'low',
                                        'normal',
                                        'high',
                                        'urgent',
                                    ]),
                                ]}
                            />
                        )}{' '}
                        {['start', 'finish', 'cancel'].map(
                            (action) =>
                                can(
                                    'workshop.work_order.' +
                                        (action === 'cancel'
                                            ? 'assign'
                                            : action),
                                ) && (
                                    <Action
                                        key={action}
                                        label={w(
                                            action === 'start'
                                                ? 'Boshlash'
                                                : action === 'finish'
                                                  ? 'Tayyor'
                                                  : 'Bekor qilish',
                                            action === 'start'
                                                ? 'Начать'
                                                : action === 'finish'
                                                  ? 'Готово'
                                                  : 'Отменить',
                                            action,
                                        )}
                                        path={path + '/' + action}
                                    />
                                ),
                        )}
                        {can('warehouse.master_stock.consume') && (
                            <Action
                                label={w(
                                    'Material sarflash',
                                    'Списать материал',
                                    'Consume material',
                                )}
                                path={path + '/consume'}
                                fields={[
                                    select(
                                        'item_id',
                                        items.map((i) => String(i.id)),
                                    ),
                                    lookup('location_id', '/locations', true),
                                ]}
                            />
                        )}{' '}
                        {can('workshop.work_order.defect') && (
                            <Action
                                label={w(
                                    'Brak / qayta ishlash',
                                    'Дефект / переделка',
                                    'Defect / rework',
                                )}
                                path={path + '/defect'}
                                fields={[
                                    select(
                                        'item_id',
                                        items.map((i) => String(i.id)),
                                    ),
                                    select('reason', [
                                        'supplier_defect',
                                        'transport_damage',
                                        'master_error',
                                        'customer_request',
                                    ]),
                                    f('note', 'textarea'),
                                    f('quantity', 'number', true),
                                ]}
                            />
                        )}
                    </>
                )}
                {resource.path === 'expenses' &&
                    can('finance.expense.approve') && (
                        <Action
                            label={w('Tasdiqlash', 'Одобрить', 'Approve')}
                            path={path + '/approve'}
                        />
                    )}
                {resource.path === 'debts' && (
                    <>
                        {can('sales.payment.create') && (
                            <Action
                                label={w(
                                    'Qarz to‘lovini qabul qilish',
                                    'Принять погашение долга',
                                    'Accept debt payment',
                                )}
                                path={'/orders/' + r.order_id + '/payments'}
                                fields={[
                                    f('amount', 'decimal'),
                                    select('method', [
                                        'cash',
                                        'card',
                                        'transfer',
                                    ]),
                                ]}
                            />
                        )}
                        {can('finance.debt.remind') && (
                            <Action
                                label={w(
                                    'Eslatma yozish',
                                    'Записать напоминание',
                                    'Log reminder',
                                )}
                                path={path + '/remind'}
                                fields={[
                                    select('channel', [
                                        'call',
                                        'in_person',
                                        'sms',
                                    ]),
                                    f('note', 'textarea', true),
                                ]}
                            />
                        )}{' '}
                        {can('finance.debt.write_off') && (
                            <Action
                                label={w(
                                    'Qarzni hisobdan chiqarish',
                                    'Списать долг',
                                    'Write off debt',
                                )}
                                path={path + '/write-off'}
                                fields={[f('reason', 'textarea')]}
                            />
                        )}
                    </>
                )}
                {resource.path === 'cash/movements' &&
                    can('finance.cash_movement.reverse') && (
                        <Action
                            label={w('Storno', 'Сторно', 'Reverse')}
                            path={path + '/reverse'}
                            fields={[f('reason', 'textarea')]}
                        />
                    )}
                {resource.path === 'bonus-entries' && (
                    <>
                        {can('payroll.bonus.approve') && (
                            <Action
                                label={w('Tasdiqlash', 'Одобрить', 'Approve')}
                                path={path + '/approve'}
                            />
                        )}{' '}
                        {can('payroll.bonus.pay') && (
                            <Action
                                label={w('To‘lash', 'Выплатить', 'Pay')}
                                path={path + '/pay'}
                                fields={[lookup('branch_id', '/branches')]}
                            />
                        )}
                    </>
                )}
                {resource.path === 'devices' && can('core.device.register') && (
                    <DeviceSettings row={r} />
                )}
                {resource.path === 'devices' && can('core.device.revoke') && (
                    <Action
                        label={w(
                            'Qurilmani bloklash',
                            'Отозвать устройство',
                            'Revoke device',
                        )}
                        path={path + '/revoke'}
                    />
                )}
                {resource.path === 'users' && (
                    <>
                        {can('core.user.assign_role') && (
                            <>
                                <RolesForm row={r} />
                                <BranchAssignments row={r} />
                            </>
                        )}{' '}
                        {can('core.user.set_debt_limit') && (
                            <Action
                                label={w(
                                    'Qarz limiti',
                                    'Лимит долга',
                                    'Debt limit',
                                )}
                                path={path + '/debt-limit'}
                                method="put"
                                fields={[f('debt_limit', 'decimal')]}
                            />
                        )}{' '}
                        {can('core.user.set_pin') && (
                            <Action
                                label="PIN"
                                path={path + '/pin'}
                                method="put"
                                fields={[f('pin', 'password')]}
                            />
                        )}
                    </>
                )}
                {resource.path === 'products' && <ProductActions row={r} />}{' '}
                {resource.path === 'customers' && <CustomerHistory row={r} />}{' '}
                {resource.path === 'suppliers' &&
                    can('finance.supplier.balance.view') && (
                        <SupplierBalance id={r.id} />
                    )}
            </div>
        </section>
    )
}
function RolesForm({ row }: { row: Row }) {
    const w = useWords(),
        write = useWrite(),
        [roles, setRoles] = useState<string[]>(
            Array.isArray(row.roles) ? (row.roles as string[]) : [],
        )
    return (
        <details className="w-full">
            <summary>{w('Rollar', 'Роли', 'Roles')}</summary>
            <div className="my-3 flex flex-wrap gap-4">
                {[
                    'director',
                    'branch_manager',
                    'seller',
                    'doctor',
                    'master',
                    'warehouse',
                    'driver',
                    'accountant',
                ].map((role) => (
                    <label key={role}>
                        <input
                            type="checkbox"
                            checked={roles.includes(role)}
                            onChange={(e) =>
                                setRoles(
                                    e.target.checked
                                        ? [...roles, role]
                                        : roles.filter((r) => r !== role),
                                )
                            }
                        />{' '}
                        {role}
                    </label>
                ))}
            </div>
            <DomainForm
                fields={[]}
                resetKey={JSON.stringify(roles)}
                onSave={() =>
                    write('/users/' + row.id + '/roles', { roles }, 'put')
                }
            />
        </details>
    )
}
function SupplierBalance({ id }: { id: number }) {
    const w = useWords(),
        q = useList('/suppliers/' + id + '/balance'),
        ledger = useList('/supplier-transactions', {
            filter: { supplier_id: id },
        })
    const data = (q.data as { data?: Row })?.data
    return (
        <div className="w-full space-y-3">
            <ErrorNotice error={q.error ?? ledger.error} />
            <p className="text-sm text-neutral-600">
                {w(
                    'Manfiy balans — biz qarzdormiz. Musbat — ta’minotchi bizga qarzdor.',
                    'Отрицательный баланс — мы должны. Положительный — поставщик должен нам.',
                    'A negative balance means we owe the supplier. A positive balance means the supplier owes us.',
                )}
            </p>
            <DataTable
                data={data ? [{ ...data, id }] : []}
                columns={['supplier_id', 'balance']}
            />
            <DataTable
                data={rows(ledger.data)}
                columns={['id', 'type', 'amount', 'reason', 'created_at']}
            />
        </div>
    )
}
function CustomerHistory({ row }: { row: Row }) {
    const w = useWords(),
        permissions = useSession((s) => s.permissions),
        orders = useList(
            '/orders',
            { filter: { customer_id: row.id } },
            permissions.includes('sales.order.view_any'),
        ),
        prescriptions = useList(
            '/customers/' + row.id + '/prescriptions',
            {},
            permissions.includes('clinic.prescription.view_history'),
        )
    const history = rows(prescriptions.data).map((rx) => ({
        ...rx,
        ...Object.fromEntries(
            ['od', 'os'].flatMap((eye) =>
                Object.entries((rx[eye] ?? {}) as Record<string, unknown>).map(
                    ([key, value]) => [eye + '_' + key, value],
                ),
            ),
        ),
    }))
    return (
        <div className="w-full space-y-4">
            <h3 className="font-semibold">
                {w('Mijoz tarixi', 'История клиента', 'Customer history')}
            </h3>
            {permissions.includes('sales.order.view_any') && (
                <DataTable
                    data={rows(orders.data)}
                    columns={['number', 'status', 'total', 'paid', 'debt']}
                />
            )}
            <ErrorNotice error={prescriptions.error} />
            {permissions.includes('clinic.prescription.view_history') && (
                <DataTable
                    data={history}
                    columns={[
                        'id',
                        'od_sph',
                        'od_cyl',
                        'od_axis',
                        'os_sph',
                        'os_cyl',
                        'os_axis',
                        'pd',
                        'created_at',
                    ]}
                />
            )}
        </div>
    )
}
function TransferReceive({ row }: { row: Row }) {
    const write = useWrite(),
        w = useWords(),
        items = rows(row.items),
        [qty, setQty] = useState<Record<string, string>>({})
    return (
        <details className="w-full">
            <summary>
                {w(
                    'Qabul qilish — haqiqiy miqdor',
                    'Принять — фактическое количество',
                    'Receive — counted quantities',
                )}
            </summary>
            {items.map((i) => (
                <label className="my-2 flex justify-between" key={i.id}>
                    {textValue(i.variant ?? i.variant_id)}{' '}
                    <input
                        className="w-24 rounded border p-2"
                        type="number"
                        min="0"
                        value={qty[i.id] ?? String(i.qty_sent ?? i.quantity)}
                        onChange={(e) =>
                            setQty((v) => ({ ...v, [i.id]: e.target.value }))
                        }
                    />
                </label>
            ))}
            <DomainForm
                resetKey={JSON.stringify(qty)}
                fields={[]}
                onSave={() =>
                    write('/transfers/' + row.id + '/receive', {
                        items: items.map((i) => ({
                            item_id: i.id,
                            quantity: Number(
                                qty[i.id] ?? i.qty_sent ?? i.quantity,
                            ),
                        })),
                    })
                }
            />
        </details>
    )
}
function ReturnForm({ order }: { order: Row }) {
    const write = useWrite(),
        w = useWords(),
        [qty, setQty] = useState<Record<string, string>>({}),
        [restock, setRestock] = useState(false)
    return (
        <details className="w-full">
            <summary className="cursor-pointer py-2">
                {w('Qaytarish', 'Возврат', 'Return')}
            </summary>
            {rows(order.items).map((i) => (
                <label className="my-2 flex justify-between" key={i.id}>
                    {textValue(i.name ?? i.variant ?? i.id)}{' '}
                    <input
                        aria-label={w(
                            'Qaytarish miqdori',
                            'Количество возврата',
                            'Return quantity',
                        )}
                        className="w-24 rounded border p-2"
                        type="number"
                        min="0"
                        max={
                            Number(i.quantity) -
                            Number(i.returned_quantity ?? 0)
                        }
                        value={qty[i.id] ?? '0'}
                        onChange={(e) =>
                            setQty((v) => ({ ...v, [i.id]: e.target.value }))
                        }
                    />
                </label>
            ))}
            <label className="my-4 flex items-center gap-2 text-sm">
                <input
                    type="checkbox"
                    checked={restock}
                    onChange={(e) => setRestock(e.target.checked)}
                />
                {w(
                    'Tovarni sotiladigan qoldiqqa qaytarish',
                    'Вернуть товар в пригодный к продаже остаток',
                    'Return items to saleable stock',
                )}
            </label>
            <DomainForm
                resetKey={JSON.stringify({ qty, restock })}
                fields={[
                    select('reason', [
                        'customer_changed_mind',
                        'wrong_prescription',
                        'product_defect',
                        'wrong_item',
                        'other',
                    ]),
                    f('refund', 'decimal', true),
                    select('refund_method', ['cash', 'card', 'transfer'], true),
                ]}
                onSave={(body) =>
                    write('/orders/' + order.id + '/returns', {
                        ...body,
                        items: rows(order.items)
                            .filter((i) => Number(qty[i.id]) > 0)
                            .map((i) => ({
                                order_item_id: i.id,
                                quantity: Number(qty[i.id]),
                                restock,
                            })),
                    })
                }
            />
        </details>
    )
}
function ProductActions({ row }: { row: Row }) {
    const w = useWords(),
        permissions = useSession((s) => s.permissions),
        v = useList('/products/' + row.id + '/variants')
    return (
        <div className="w-full space-y-3">
            {permissions.includes('catalog.product.approve') && (
                <Action
                    label={w('Tasdiqlash', 'Одобрить', 'Approve')}
                    path={'/products/' + row.id + '/approve'}
                    initial={{ approved: true }}
                    fields={[select('approved', ['true', 'false'])]}
                />
            )}{' '}
            {permissions.includes('catalog.product.merge') && (
                <Action
                    label={w('Birlashtirish', 'Объединить', 'Merge')}
                    path={'/products/' + row.id + '/merge'}
                    fields={[lookup('merge_into_id', '/products')]}
                />
            )}
            <DataTable
                data={rows(v.data)}
                columns={['sku', 'barcode', 'optical_label', 'color', 'size']}
            />
            {permissions.includes('catalog.variant.create') && (
                <Action
                    label={w(
                        'Variant qo‘shish',
                        'Добавить вариант',
                        'Add variant',
                    )}
                    path={'/products/' + row.id + '/variants'}
                    fields={[
                        f('sku', 'text', true),
                        f('barcode', 'text', true),
                        f('sph', 'decimal', true),
                        f('cyl', 'decimal', true),
                        f('axis', 'number', true),
                        f('add', 'decimal', true),
                        f('color', 'text', true),
                        f('size', 'text', true),
                    ]}
                />
            )}{' '}
            {permissions.includes('catalog.variant.update') &&
                rows(v.data).map((variant) => (
                    <Action
                        key={'edit-' + variant.id}
                        label={
                            w(
                                'Variantni tahrirlash',
                                'Изменить вариант',
                                'Edit variant',
                            ) +
                            ' · ' +
                            textValue(variant.sku)
                        }
                        path={'/products/' + row.id + '/variants/' + variant.id}
                        method="put"
                        initial={variant}
                        fields={[
                            f('sku', 'text', true),
                            f('barcode', 'text', true),
                            f('sph', 'decimal', true),
                            f('cyl', 'decimal', true),
                            f('axis', 'number', true),
                            f('add', 'decimal', true),
                            f('index', 'decimal', true),
                            select(
                                'coating',
                                [
                                    'none',
                                    'hmc',
                                    'shmc',
                                    'blue',
                                    'photochromic',
                                    'polarized',
                                    'mirror',
                                ],
                                true,
                            ),
                            f('diameter', 'number', true),
                            f('color', 'text', true),
                            f('size', 'text', true),
                            select('is_active', ['true', 'false'], true),
                        ]}
                    />
                ))}
            {permissions.includes('catalog.price.view') &&
                rows(v.data).map((variant) => (
                    <PriceHistory
                        key={'history-' + variant.id}
                        id={variant.id}
                        sku={textValue(variant.sku)}
                    />
                ))}
            {rows(v.data).map(
                (variant) =>
                    permissions.includes('catalog.price.update') && (
                        <Action
                            key={variant.id}
                            label={
                                w('Narx', 'Цена', 'Price') +
                                ' · ' +
                                textValue(variant.sku)
                            }
                            path={'/variants/' + variant.id + '/prices'}
                            fields={[
                                f('price', 'decimal'),
                                lookup('branch_id', '/branches', true),
                                f('valid_from', 'date', true),
                            ]}
                        />
                    ),
            )}
        </div>
    )
}
const statusOptions: Record<string, string[]> = {
    orders: [
        'new',
        'awaiting_exam',
        'prescription_ready',
        'materials_reserved',
        'awaiting_transfer',
        'customer_notified',
        'returned',
        'in_workshop',
        'ready',
        'delivered',
        'closed',
        'cancelled',
        'rework',
    ],
    transfers: [
        'draft',
        'sent',
        'in_transit',
        'received',
        'partially_received',
        'cancelled',
    ],
    purchases: ['draft', 'received', 'cancelled'],
    visits: ['waiting', 'in_progress', 'finished', 'no_show', 'cancelled'],
    debts: ['open', 'overdue', 'doubtful', 'paid', 'written_off'],
    products: ['pending', 'approved', 'rejected'],
    'stock-requests': [
        'pending',
        'approved',
        'rejected',
        'fulfilled',
        'cancelled',
    ],
}
export function ResourcePage({ resource: supplied }: { resource?: Resource }) {
    const { section } = useParams(),
        resource = supplied ?? resources.find((r) => r.path === section),
        w = useWords(),
        fieldLabel = useLabel(),
        permissions = useSession((s) => s.permissions)
    const [search, setSearch] = useState(''),
        [status, setStatus] = useState(''),
        [visitDate, setVisitDate] = useState(
            new Intl.DateTimeFormat('en-CA', {
                timeZone: 'Asia/Tashkent',
                year: 'numeric',
                month: '2-digit',
                day: '2-digit',
            }).format(new Date()),
        ),
        [page, setPage] = useState(1),
        [create, setCreate] = useState(
            !!resource?.fields && !permissions.includes(resource.permission),
        ),
        [selected, setSelected] = useState<Row | null>(null)
    const q = useList(
        '/' + (resource?.path ?? ''),
        {
            per_page: 25,
            page,
            ...(resource?.path === 'products' && search ? { search } : {}),
            filter: {
                ...(search && resource?.path !== 'products'
                    ? {
                          [resource?.path === 'customers' &&
                          /^[+\d]/.test(search)
                              ? 'phone'
                              : 'name']: search,
                      }
                    : {}),
                ...(status ? { status } : {}),
                ...(resource?.path === 'visits' && visitDate
                    ? { queue_date: visitDate }
                    : {}),
            },
        },
        !!resource &&
            (permissions.includes(resource.permission) ||
                permissions.includes(resource.alternatePermission ?? '')),
    )
    if (!resource) return null
    if (
        !permissions.includes(resource.permission) &&
        !permissions.includes(resource.alternatePermission ?? '') &&
        !permissions.includes(resource.createPermission ?? '')
    )
        return (
            <p role="alert">
                {w('Ruxsat yo‘q', 'Нет доступа', 'Access denied')}
            </p>
        )
    const meta =
        (q.data as { meta?: { last_page?: number; total?: number } })?.meta ??
        (q.data as { last_page?: number; total?: number } | undefined)
    return (
        <div className="space-y-5">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <h1 className="text-2xl font-semibold tracking-tight">
                    {w(...resource.title)}
                </h1>
                {resource.fields &&
                    permissions.includes(resource.createPermission ?? '') && (
                        <button
                            className="action-primary"
                            onClick={() => setCreate(!create)}
                        >
                            {create
                                ? w('Yopish', 'Закрыть', 'Close')
                                : w('Yangi yozuv', 'Создать', 'Create')}
                        </button>
                    )}
            </div>
            {resource.path === 'cash/movements' && <CashSummary />}
            {create && (
                <section className="rounded-xl border border-neutral-200 bg-white p-5">
                    <ResourceCreate
                        resource={resource}
                        onDone={() => setCreate(false)}
                    />
                </section>
            )}
            {statusOptions[resource.path] && (
                <label className="grid max-w-xs gap-1 text-sm">
                    {w('Holat', 'Статус', 'Status')}
                    <select
                        className="rounded-lg border border-neutral-300 bg-white p-2"
                        value={status}
                        onChange={(e) => {
                            setStatus(e.target.value)
                            setPage(1)
                        }}
                    >
                        <option value="">
                            {w(
                                'Barcha holatlar',
                                'Все статусы',
                                'All statuses',
                            )}
                        </option>
                        {statusOptions[resource.path].map((v) => (
                            <option key={v} value={v}>
                                {fieldLabel(v)}
                            </option>
                        ))}
                    </select>
                </label>
            )}
            {resource.path === 'visits' && (
                <label className="grid max-w-xs gap-1 text-sm">
                    {w('Sana', 'Дата', 'Date')}
                    <input
                        className="rounded-lg border border-neutral-300 bg-white p-2"
                        type="date"
                        value={visitDate}
                        onChange={(e) => {
                            setVisitDate(e.target.value)
                            setPage(1)
                        }}
                    />
                </label>
            )}
            {selected && (
                <ResourceDetails
                    key={selected.id}
                    resource={resource}
                    row={selected}
                    onClose={() => setSelected(null)}
                />
            )}
            <div className="flex flex-wrap items-center justify-between gap-3">
                {[
                    'products',
                    'customers',
                    'suppliers',
                    'users',
                    'branches',
                    'brands',
                    'categories',
                    'services',
                ].includes(resource.path) && (
                    <input
                        aria-label={w('Qidirish', 'Поиск', 'Search')}
                        type="search"
                        className="w-full max-w-sm rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm"
                        placeholder={w(
                            'Ism yoki nom bo‘yicha qidirish',
                            'Поиск по имени или названию',
                            'Search by name',
                        )}
                        value={search}
                        onChange={(e) => {
                            setSearch(e.target.value)
                            setPage(1)
                        }}
                    />
                )}
                <button
                    className="action-secondary"
                    onClick={() => void q.refetch()}
                >
                    {w('Yangilash', 'Обновить', 'Refresh')}
                </button>
            </div>
            <ErrorNotice error={q.error} />
            {!permissions.includes(resource.permission) &&
            !permissions.includes(
                resource.alternatePermission ?? '',
            ) ? null : q.isPending ? (
                <Skeleton />
            ) : (
                <DataTable
                    data={rows(q.data)}
                    columns={resource.columns}
                    onSelect={setSelected}
                />
            )}
            <div className="flex items-center justify-between text-sm">
                <span>
                    {meta?.total ?? rows(q.data).length}{' '}
                    {w('yozuv', 'записей', 'records')}
                </span>
                <div className="flex items-center gap-3">
                    <button
                        className="action-secondary"
                        disabled={page <= 1}
                        onClick={() => setPage(page - 1)}
                    >
                        {w('Oldingi', 'Назад', 'Previous')}
                    </button>
                    <span>
                        {page} / {meta?.last_page ?? 1}
                    </span>
                    <button
                        className="action-secondary"
                        disabled={page >= (meta?.last_page ?? 1)}
                        onClick={() => setPage(page + 1)}
                    >
                        {w('Keyingi', 'Далее', 'Next')}
                    </button>
                </div>
            </div>
        </div>
    )
}
function PrescriptionCard({ row }: { row: Row }) {
    const w = useWords()
    return (
        <div className="w-full rounded-xl border border-neutral-200 p-5">
            <h3 className="mb-4 text-lg font-semibold">
                OPTIKA · {w('Retsept', 'Рецепт', 'Prescription')} #{row.id}
            </h3>
            <div className="grid grid-cols-2 gap-6">
                {['od', 'os'].map((eye) => (
                    <div key={eye}>
                        <p className="font-semibold">{eye.toUpperCase()}</p>
                        {Object.entries(
                            (row[eye] ?? {}) as Record<string, unknown>,
                        ).map(([key, value]) => (
                            <p key={key} className="mt-2 text-lg tabular-nums">
                                {key.toUpperCase()}: {textValue(value)}
                            </p>
                        ))}
                    </div>
                ))}
            </div>
            <p className="mt-4">
                PD: {textValue(row.pd)} · {textValue(row.valid_until)}
            </p>
            {row.is_expired === true && (
                <p className="mt-3 text-amber-800">
                    {w(
                        'Retsept muddati o‘tgan. Qayta ko‘rik tavsiya etiladi.',
                        'Рецепт просрочен. Рекомендуется повторный осмотр.',
                        'Prescription expired. A checkup is recommended.',
                    )}
                </p>
            )}
            <button
                className="action-secondary mt-4"
                onClick={() => window.print()}
            >
                {w('Chop etish / PDF', 'Печать / PDF', 'Print / PDF')}
            </button>
        </div>
    )
}
function AuditChanges({ row }: { row: Row }) {
    const props = (row.properties ?? {}) as Record<string, unknown>,
        old = (props.old ?? {}) as Record<string, unknown>,
        after = (props.attributes ?? {}) as Record<string, unknown>
    const keys = [...new Set([...Object.keys(old), ...Object.keys(after)])]
    return (
        <DataTable
            data={keys.map((key, index) => ({
                id: index,
                field: key,
                before: textValue(old[key]),
                after: textValue(after[key]),
            }))}
            columns={['field', 'before', 'after']}
        />
    )
}
function PriceHistory({ id, sku }: { id: number; sku: string }) {
    const w = useWords(),
        [open, setOpen] = useState(false),
        q = useList('/variants/' + id + '/prices', {}, open)
    return (
        <details onToggle={(e) => setOpen(e.currentTarget.open)}>
            <summary className="cursor-pointer py-2 text-sm">
                {w('Narx tarixi', 'История цен', 'Price history')} · {sku}
            </summary>
            {open && (
                <>
                    <ErrorNotice error={q.error} />
                    <DataTable
                        data={rows(q.data)}
                        columns={[
                            'price',
                            'branch_id',
                            'valid_from',
                            'valid_to',
                        ]}
                    />
                </>
            )}
        </details>
    )
}
function InventoryCounts({ row }: { row: Row }) {
    const w = useWords(),
        label = useLabel(),
        permissions = useSession((s) => s.permissions),
        write = useWrite(),
        [counts, setCounts] = useState<Record<string, string>>({}),
        items = rows(row.items),
        path = '/inventories/' + row.id,
        can = (p: string) => permissions.includes('warehouse.inventory.' + p),
        editable =
            can('count') && ['draft', 'counting'].includes(String(row.status))
    return (
        <div className="w-full space-y-4">
            {items.map((item) => (
                <label
                    key={item.id}
                    className="flex flex-wrap items-center justify-between gap-3 text-sm"
                >
                    {textValue(item.variant ?? item.variant_id)} ·{' '}
                    {w('Hisob', 'Учёт', 'Expected')}:{' '}
                    {textValue(item.expected_qty)}
                    <input
                        aria-label={label('actual_qty')}
                        className="w-28 rounded-lg border border-neutral-300 p-2"
                        type="number"
                        min="0"
                        max="1000000"
                        step="1"
                        disabled={!editable}
                        value={
                            counts[String(item.variant_id)] ??
                            (item.actual_qty == null
                                ? ''
                                : String(item.actual_qty))
                        }
                        onChange={(e) =>
                            setCounts((c) => ({
                                ...c,
                                [String(item.variant_id)]: e.target.value,
                            }))
                        }
                    />
                </label>
            ))}
            {editable && (
                <>
                    <DomainForm
                        key={JSON.stringify(counts)}
                        fields={[]}
                        submitLabel={w(
                            'Sanoqni saqlash',
                            'Сохранить подсчёт',
                            'Save counts',
                        )}
                        onSave={() =>
                            write(
                                path + '/counts',
                                {
                                    items: items
                                        .filter((item) => {
                                            const value =
                                                counts[
                                                    String(item.variant_id)
                                                ] ?? item.actual_qty
                                            return (
                                                value !== null &&
                                                value !== undefined &&
                                                value !== ''
                                            )
                                        })
                                        .map((item) => ({
                                            variant_id: item.variant_id,
                                            actual_qty: Number(
                                                counts[
                                                    String(item.variant_id)
                                                ] ?? item.actual_qty,
                                            ),
                                        })),
                                },
                                'put',
                            )
                        }
                    />
                    <details>
                        <summary className="cursor-pointer py-2 text-sm">
                            {w(
                                'Qo‘shimcha topilgan tovar',
                                'Дополнительно найденный товар',
                                'Additional counted item',
                            )}
                        </summary>
                        <DomainForm
                            fields={[
                                lookup('variant_id', '/catalog-variants'),
                                f('actual_qty', 'number'),
                                f('unit_cost', 'decimal', true),
                            ]}
                            onSave={(body) =>
                                write(
                                    path + '/counts',
                                    { items: [body] },
                                    'put',
                                )
                            }
                        />
                    </details>
                </>
            )}
            <div className="flex flex-wrap gap-3">
                {['review', 'complete', 'cancel']
                    .filter(
                        (action) =>
                            can(action === 'cancel' ? 'start' : action) &&
                            (action === 'review'
                                ? ['draft', 'counting'].includes(
                                      String(row.status),
                                  )
                                : action === 'complete'
                                  ? row.status === 'review'
                                  : !['completed', 'cancelled'].includes(
                                        String(row.status),
                                    )),
                    )
                    .map((action) => (
                        <Action
                            key={action}
                            label={label(action)}
                            path={path + '/' + action}
                        />
                    ))}
            </div>
        </div>
    )
}
function CashSummary() {
    const w = useWords(),
        label = useLabel(),
        q = useList('/cash/summary')
    const body = (q.data as { data?: Row | null })?.data
    return (
        <section className="space-y-3 rounded-xl border border-neutral-200 bg-white p-5">
            <h2 className="text-lg font-semibold">
                {w(
                    'Joriy smena kassasi',
                    'Касса текущей смены',
                    'Current shift cash',
                )}
            </h2>
            <ErrorNotice error={q.error} />
            {body ? (
                <>
                    <dl className="grid gap-4 sm:grid-cols-3">
                        {['shift_id', 'opening_cash', 'expected_cash'].map(
                            (key) => (
                                <div key={key}>
                                    <dt className="text-sm text-neutral-600">
                                        {label(key)}
                                    </dt>
                                    <dd className="mt-1 text-xl font-semibold tabular-nums">
                                        {displayValue(key, body[key], label)}
                                    </dd>
                                </div>
                            ),
                        )}
                    </dl>
                    <DataTable
                        data={rows(body.by_category)}
                        columns={['category', 'net']}
                    />
                </>
            ) : (
                <p className="text-sm text-neutral-600">
                    {w(
                        'Ochiq smena yo‘q. Smenani oching.',
                        'Нет открытой смены. Откройте смену.',
                        'No open shift. Open a shift first.',
                    )}
                </p>
            )}
        </section>
    )
}
function RoleChoices({
    value,
    onChange,
}: {
    value: string[]
    onChange: (value: string[]) => void
}) {
    const label = useLabel()
    return (
        <fieldset className="my-4">
            <legend className="mb-3 text-sm font-medium">
                {label('roles')}
            </legend>
            <div className="flex flex-wrap gap-4">
                {[
                    'director',
                    'branch_manager',
                    'seller',
                    'doctor',
                    'master',
                    'warehouse',
                    'driver',
                    'accountant',
                ].map((role) => (
                    <label
                        key={role}
                        className="flex items-center gap-2 text-sm"
                    >
                        <input
                            type="checkbox"
                            checked={value.includes(role)}
                            onChange={(e) =>
                                onChange(
                                    e.target.checked
                                        ? [...value, role]
                                        : value.filter((v) => v !== role),
                                )
                            }
                        />
                        {label(role)}
                    </label>
                ))}
            </div>
        </fieldset>
    )
}
function DeviceSettings({ row }: { row: Row }) {
    const w = useWords(),
        write = useWrite(),
        [roles, setRoles] = useState<string[]>(
            Array.isArray(row.allowed_roles)
                ? (row.allowed_roles as string[])
                : [],
        )
    return (
        <details className="w-full">
            <summary className="cursor-pointer py-2 text-sm">
                {w(
                    'Qurilma rollari va nomi',
                    'Роли и название устройства',
                    'Device roles and name',
                )}
            </summary>
            <RoleChoices value={roles} onChange={setRoles} />
            <DomainForm
                resetKey={JSON.stringify(roles)}
                fields={[f('name')]}
                initial={row}
                onSave={(body) =>
                    write(
                        '/devices/' + row.id,
                        { ...body, allowed_roles: roles },
                        'put',
                    )
                }
            />
        </details>
    )
}
function BranchAssignments({ row }: { row: Row }) {
    const w = useWords(),
        q = useList('/branches', { per_page: 100 }),
        write = useWrite(),
        [selected, setSelected] = useState<number[]>(
            Array.isArray(row.branch_ids) ? (row.branch_ids as number[]) : [],
        )
    const remote = JSON.stringify(row.branch_ids ?? [])
    useEffect(() => {
        setSelected(JSON.parse(remote) as number[])
    }, [remote])
    return (
        <details className="w-full">
            <summary className="cursor-pointer py-2 text-sm">
                {w(
                    'Qo‘shimcha filiallar',
                    'Дополнительные филиалы',
                    'Additional branches',
                )}
            </summary>
            <ErrorNotice error={q.error} />
            <div className="my-4 grid gap-3 sm:grid-cols-2">
                {rows(q.data).map((branch) => (
                    <label
                        key={branch.id}
                        className="flex items-center gap-2 text-sm"
                    >
                        <input
                            type="checkbox"
                            checked={selected.includes(branch.id)}
                            onChange={(e) =>
                                setSelected((value) =>
                                    e.target.checked
                                        ? [...value, branch.id]
                                        : value.filter(
                                              (id) => id !== branch.id,
                                          ),
                                )
                            }
                        />
                        {textValue(branch.name)}
                    </label>
                ))}
            </div>
            <DomainForm
                fields={[]}
                resetKey={JSON.stringify(selected)}
                onSave={() =>
                    write(
                        '/users/' + row.id + '/branches',
                        { branch_ids: selected },
                        'put',
                    )
                }
            />
        </details>
    )
}
