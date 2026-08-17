<?php

declare(strict_types=1);

namespace App\Modules\Finance\Enums;

/**
 * Qarz holati — ENUMS.md §8, BOSQICH-10.md §10a.
 *
 * `open`/`overdue`/`doubtful` **vaqtga bog'liq** — `DebtRegistry`
 * kunlik `finance:refresh-debt-statuses` orqali qayta hisoblaydi,
 * to'lov hodisasisiz ham o'zgarishi kerak. `paid`/`written_off` esa
 * yakuniy holatlar — ulardan qaytish yo'q.
 */
enum DebtStatus: string
{
    case Open = 'open';
    case Overdue = 'overdue';
    case Doubtful = 'doubtful';
    case Paid = 'paid';
    case WrittenOff = 'written_off';

    public function isFinal(): bool
    {
        return $this === self::Paid || $this === self::WrittenOff;
    }
}
