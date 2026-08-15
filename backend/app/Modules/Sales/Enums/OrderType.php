<?php

declare(strict_types=1);

namespace App\Modules\Sales\Enums;

/**
 * Buyurtma turi — ENUMS.md §4.
 *
 * Farqi bitta, lekin muhim: tez savdoda tovar **darrov** beriladi,
 * shuning uchun chek yaratilishi bilan topshirilgan hisoblanadi va
 * daromad o'sha zahoti tan olinadi (7.8). Buyurtmada esa topshirish
 * alohida amal — linza yasalishi kerak.
 */
enum OrderType: string
{
    /** Tez savdo (chek) — `new → delivered → closed`. */
    case Quick = 'quick';

    /** Buyurtma — to'liq holat zanjiri (7.3). */
    case Order = 'order';

    /**
     * Yaratilishi bilan topshiriladimi.
     */
    public function deliversImmediately(): bool
    {
        return $this === self::Quick;
    }
}
