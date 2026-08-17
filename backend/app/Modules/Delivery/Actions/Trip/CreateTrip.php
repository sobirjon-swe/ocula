<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Actions\Trip;

use App\Modules\Core\Models\User;
use App\Modules\Delivery\Enums\TripStatus;
use App\Modules\Delivery\Models\Trip;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/**
 * Yo'l varaqasi yaratish — PROJECT.md §6.7, BOSQICH-8.md §5 #1.
 *
 * Dispetcher (director/branch_manager/warehouse) quradi — haydovchida
 * `delivery.trip.create` yo'q, marshrutni odam tuzadi.
 *
 * Bir haydovchi bir kunda bitta reysga ega (SCHEMA.md
 * `unique(driver_id, date)`).
 */
final class CreateTrip
{
    public function handle(User $author, User $driver, CarbonImmutable $date): Trip
    {
        $exists = Trip::query()
            ->where('driver_id', $driver->id)
            ->whereDate('date', $date)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'date' => __('delivery::trip.already_has_trip', ['date' => $date->toDateString()]),
            ]);
        }

        return Trip::create([
            'driver_id' => $driver->id,
            'date' => $date->toDateString(),
            'status' => TripStatus::Planned,
            'created_by' => $author->id,
        ]);
    }
}
