<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Actions\Trip;

use App\Modules\Delivery\Enums\TripStatus;
use App\Modules\Delivery\Models\Trip;
use Illuminate\Validation\ValidationException;

/**
 * Reysni tugatish — ENUMS.md §7.
 *
 * Hal qilinmagan to'xtashlar bo'lsa ham tugatishga ruxsat beriladi —
 * haydovchi kunni yopmoqchi bo'lishi mumkin, qolgan to'xtashlar
 * ertangi reysga o'tkaziladi (bu qo'lda, alohida amal emas).
 */
final class FinishTrip
{
    public function handle(Trip $trip): Trip
    {
        if (! $trip->status->canBeFinished()) {
            throw ValidationException::withMessages([
                'status' => __('delivery::trip.cannot_finish', ['status' => $trip->status->value]),
            ]);
        }

        $trip->update(['status' => TripStatus::Finished, 'finished_at' => now()]);

        return $trip;
    }
}
