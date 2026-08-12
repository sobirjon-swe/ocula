<?php

declare(strict_types=1);

namespace App\Modules\Warehouse\Enums;

/**
 * Kirim hujjati holati — ENUMS.md §3.
 *
 *     draft → received   (qatlamlar ochiladi)
 *     draft → cancelled
 *
 * `received` dan orqaga qaytish **yo'q** — xato bo'lsa storno (7.21).
 */
enum PurchaseStatus: string
{
    /** Kiritilmoqda — ombor tegmaydi. */
    case Draft = 'draft';

    /** Qabul qilindi — qatlamlar ochilgan. */
    case Received = 'received';

    /** Bekor qilindi (faqat `draft` dan). */
    case Cancelled = 'cancelled';

    public function isEditable(): bool
    {
        return $this === self::Draft;
    }
}
