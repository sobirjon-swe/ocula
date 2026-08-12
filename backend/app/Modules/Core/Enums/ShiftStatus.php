<?php

declare(strict_types=1);

namespace App\Modules\Core\Enums;

/**
 * Smena holati — ENUMS.md §1.
 *
 * Kunlik hisobot `closed` + `disputed` smenalar yig'indisi bo'yicha
 * hisoblanadi, kalendar sanasi bo'yicha emas (§15 #24).
 */
enum ShiftStatus: string
{
    /** Ochilgan, savdo ketmoqda. */
    case Open = 'open';

    /** Sanoq boshlandi — `actual_cash` kiritilmoqda. */
    case Closing = 'closing';

    /** Yopilgan, `difference` hisoblangan. */
    case Closed = 'closed';

    /** Kamomad/ortiqcha direktor tekshiruvida. */
    case Disputed = 'disputed';

    /** Filialda bir vaqtda faqat bitta shunday smena bo'lishi mumkin. */
    public function isActive(): bool
    {
        return $this === self::Open || $this === self::Closing;
    }

    /** Kunlik hisobotga kiradigan holatlar (§15 #24). */
    public function countsInDailyReport(): bool
    {
        return $this === self::Closed || $this === self::Disputed;
    }
}
