<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Events;

use App\Modules\Delivery\Models\TripStop;

/**
 * Mijozga buyurtma yetkazildi (haydovchi tasdig'i) — PROJECT.md 7.4,
 * BOSQICH-8.md §4.
 *
 * Telegram moduli buni tinglab, mijozga "Yetkazildimi?" savolini
 * yuboradi (`Telegram\Listeners\AskDeliveryConfirmation`). Faqat
 * `customer` turidagi to'xtashlar uchun otiladi.
 */
final class StopDelivered
{
    public function __construct(public readonly TripStop $stop) {}
}
