<?php

declare(strict_types=1);

namespace App\Modules\Sales\Enums;

/**
 * To'lov yozuvining holati — ENUMS.md §4.
 *
 * `failed` ataylab yo'q: muvaffaqiyatsiz to'lov umuman yozilmaydi.
 * `payments` insert-only jadval (7.21), unda faqat haqiqatan bo'lgan
 * pul harakati turadi.
 */
enum PaymentTxStatus: string
{
    /** Haydovchi yig'di, kassaga hali topshirilmadi (7.4). */
    case Pending = 'pending';

    /** Kassada yoki hisobda. */
    case Completed = 'completed';

    /** Storno qilingan (7.21). */
    case Reversed = 'reversed';
}
