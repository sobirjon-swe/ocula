<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Actions\TripStop;

use App\Modules\Delivery\Enums\ConfirmationStatus;
use App\Modules\Delivery\Models\TripStop;

/**
 * Mijoz "Yo'q" dedi — direktorga signal (PROJECT.md 7.4, BOSQICH-8.md §4).
 *
 * Jonli lenta UI keyingi ish (§1) — hozircha `disputed` holati
 * `GET /trips`/`GET /trip-stops` orqali ko'rinadi.
 */
final class DisputeStopDelivery
{
    public function handle(TripStop $stop): TripStop
    {
        if ($stop->confirmation_status !== ConfirmationStatus::Awaiting) {
            return $stop;
        }

        $stop->update([
            'confirmation_status' => ConfirmationStatus::Disputed,
            'customer_confirmed_at' => now(),
        ]);

        return $stop;
    }
}
