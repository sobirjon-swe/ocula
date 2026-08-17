<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Enums;

/**
 * Mukofot bazasi — ENUMS.md §9, PROJECT.md 7.12.
 */
enum BonusBase: string
{
    /** Sotuv summasidan (chegirmadan keyin). */
    case Revenue = 'revenue';

    /** Foydadan — tavsiya etilgan (keraksiz chegirmaga undamaydi). */
    case Profit = 'profit';

    /** Buyurtmalar sonidan (usta uchun). */
    case Count = 'count';
}
