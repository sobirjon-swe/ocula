<?php

declare(strict_types=1);

namespace App\Modules\Finance\Enums;

/**
 * Mijozning qarz eslatmasiga javobi — SCHEMA.md `debt_reminders`.
 *
 * Avtomatik Telegram eslatmasi doim `none` bilan yoziladi (mijoz botga
 * javob yozmaydi) — javobni xodim keyinroq qo'lda belgilaydi.
 */
enum ReminderResponse: string
{
    case None = 'none';
    case Promised = 'promised';
    case Refused = 'refused';
    case Disputed = 'disputed';
    case Paid = 'paid';
}
