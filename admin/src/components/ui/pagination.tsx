import type { PageMeta } from '@/lib/api/types'
import { useT } from '@/lib/i18n'

import { Button } from './button'

/**
 * Sahifalash. Sahifa hajmi backendda 100 ta bilan cheklangan (§10) —
 * filialdagi internet sekin, "hammasini ko'rsat" tugmasi ataylab yo'q.
 */
export function Pagination({
  meta,
  onChange,
}: {
  meta: PageMeta | undefined
  onChange: (page: number) => void
}) {
  const t = useT()

  if (meta === undefined || meta.total === 0) return null

  return (
    <div className="flex items-center justify-between gap-4 pt-3 text-sm text-neutral-600">
      <span>
        {t('table.total')}: <span className="font-medium text-neutral-900">{meta.total}</span>
      </span>

      <div className="flex items-center gap-2">
        <Button
          size="sm"
          variant="secondary"
          disabled={meta.current_page <= 1}
          onClick={() => onChange(meta.current_page - 1)}
        >
          {t('table.prev')}
        </Button>

        <span className="tabular-nums">
          {t('table.page')} {meta.current_page} / {meta.last_page}
        </span>

        <Button
          size="sm"
          variant="secondary"
          disabled={meta.current_page >= meta.last_page}
          onClick={() => onChange(meta.current_page + 1)}
        >
          {t('table.next')}
        </Button>
      </div>
    </div>
  )
}
