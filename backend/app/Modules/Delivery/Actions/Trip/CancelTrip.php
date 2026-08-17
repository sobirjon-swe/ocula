<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Actions\Trip;

use App\Modules\Delivery\Enums\TripStatus;
use App\Modules\Delivery\Models\Trip;
use Illuminate\Validation\ValidationException;

/**
 * Reysni bekor qilish — faqat `planned` holatida (ENUMS.md §7).
 */
final class CancelTrip
{
    public function handle(Trip $trip): Trip
    {
        if (! $trip->status->canBeCancelled()) {
            throw ValidationException::withMessages([
                'status' => __('delivery::trip.cannot_cancel', ['status' => $trip->status->value]),
            ]);
        }

        $trip->update(['status' => TripStatus::Cancelled]);

        return $trip;
    }
}
