<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Actions\Trip;

use App\Modules\Delivery\Enums\TripStatus;
use App\Modules\Delivery\Models\Trip;
use Illuminate\Validation\ValidationException;

/**
 * Reysni boshlash — ENUMS.md §7.
 */
final class StartTrip
{
    public function handle(Trip $trip): Trip
    {
        if (! $trip->status->canBeStarted()) {
            throw ValidationException::withMessages([
                'status' => __('delivery::trip.cannot_start', ['status' => $trip->status->value]),
            ]);
        }

        $trip->update(['status' => TripStatus::InProgress, 'started_at' => now()]);

        return $trip;
    }
}
