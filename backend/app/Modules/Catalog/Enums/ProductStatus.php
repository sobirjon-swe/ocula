<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Enums;

/**
 * Tovar tasdiqlash holati — PROJECT.md 7.17, ENUMS.md §2.
 *
 * **KRITIK QOIDA: tasdiqlash savdoni TO'SMAYDI.** Direktor telefonini
 * ko'rmasa yoki dam olishda bo'lsa, savdo to'xtamasligi kerak.
 * Ombor qoldig'i esa har doim haqiqatga mos bo'lishi shart —
 * "tovar bor, lekin tizimda yo'q" eng yomon holat.
 */
enum ProductStatus: string
{
    /** Kiritildi, direktor tasdig'i kutilmoqda. Sotiladi, qoldiqda bor. */
    case Pending = 'pending';

    /** Direktor tasdiqladi. */
    case Approved = 'approved';

    /** Rad etildi — `merged_into_id` orqali asosiy tovarga birlashtiriladi. */
    case Rejected = 'rejected';

    /** Sotish mumkinmi (7.17 jadvali). */
    public function isSellable(): bool
    {
        return $this !== self::Rejected;
    }

    /** Analitikaga kiradimi? `pending` alohida "tekshirilmagan" qatorida. */
    public function countsInAnalytics(): bool
    {
        return $this === self::Approved;
    }
}
