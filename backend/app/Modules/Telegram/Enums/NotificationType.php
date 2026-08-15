<?php

declare(strict_types=1);

namespace App\Modules\Telegram\Enums;

/**
 * Yuborilgan xabar turi — BOSQICH-7.md §4.
 */
enum NotificationType: string
{
    /** Buyurtma tayyor — PROJECT.md 7.3. */
    case OrderReady = 'order_ready';

    /** Qarz eslatmasi — PROJECT.md 7.6. */
    case DebtReminder = 'debt_reminder';

    /** Ko'rik/retsept eslatmasi — PROJECT.md 7.11. */
    case CheckupReminder = 'checkup_reminder';
}
