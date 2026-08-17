<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Enums;

/**
 * Mukofot yozuvi holati — ENUMS.md §9.
 */
enum BonusStatus: string
{
    /** Hisoblandi (`delivered` + `paid` sharti bajarildi). */
    case Accrued = 'accrued';

    /** Qaytarish sababli bekor qilindi (7.12). */
    case Reversed = 'reversed';

    /** Direktor tasdiqladi. */
    case Approved = 'approved';

    /** To'landi. */
    case Paid = 'paid';

    public function isFinal(): bool
    {
        return $this === self::Reversed || $this === self::Paid;
    }
}
