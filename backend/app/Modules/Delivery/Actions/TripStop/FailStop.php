<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Actions\TripStop;

use App\Modules\Delivery\Enums\TripStopStatus;
use App\Modules\Delivery\Models\TripStop;
use Illuminate\Validation\ValidationException;

/**
 * Topshira olmadi — mijoz yo'q, rad etdi va h.k. (ENUMS.md §7).
 */
final class FailStop
{
    public function handle(TripStop $stop, string $reason): TripStop
    {
        if (! $stop->status->canFail()) {
            throw ValidationException::withMessages([
                'status' => __('delivery::stop.cannot_fail', ['status' => $stop->status->value]),
            ]);
        }

        $stop->update([
            'status' => TripStopStatus::Failed,
            'fail_reason' => $reason,
        ]);

        return $stop;
    }
}
