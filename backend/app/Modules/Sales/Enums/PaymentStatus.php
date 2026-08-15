<?php

declare(strict_types=1);

namespace App\Modules\Sales\Enums;

/**
 * Buyurtmaning to'lov holati — ENUMS.md §4.
 *
 *     unpaid → partial → paid
 *            └→ debt (muddat o'tgan)
 *
 * `status` bilan **aralashtirilmaydi** (7.3): bu mustaqil ikkinchi o'q.
 *
 * `debt` ni qo'yadigan mexanizm (muddat o'tishi) Finance bosqichida
 * keladi — bu bosqichda qiymat bor, lekin uni hech kim qo'ymaydi.
 */
enum PaymentStatus: string
{
    case Unpaid = 'unpaid';
    case Partial = 'partial';
    case Paid = 'paid';

    /** Muddati o'tgan qarz (7.6). */
    case Debt = 'debt';

    /** To'liq qaytarilgan. */
    case Refunded = 'refunded';
}
