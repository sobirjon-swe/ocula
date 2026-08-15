<?php

declare(strict_types=1);

namespace App\Modules\Finance\Enums;

/**
 * Kassa harakati yo'nalishi — ENUMS.md §8.
 *
 * `cash_movements.amount` har doim musbat, ishorani shu enum beradi.
 */
enum CashDirection: string
{
    /** Pul kassaga tushdi. */
    case In = 'in';

    /** Pul kassadan chiqdi. */
    case Out = 'out';

    /** Yig'indi hisoblashda ishlatiladigan ishora: `+1` yoki `−1`. */
    public function sign(): int
    {
        return $this === self::In ? 1 : -1;
    }

    public function opposite(): self
    {
        return $this === self::In ? self::Out : self::In;
    }
}
