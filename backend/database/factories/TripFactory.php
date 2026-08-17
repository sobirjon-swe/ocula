<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Core\Models\User;
use App\Modules\Delivery\Enums\TripStatus;
use App\Modules\Delivery\Models\Trip;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Trip>
 */
class TripFactory extends Factory
{
    protected $model = Trip::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'driver_id' => User::factory(),
            'date' => now()->toDateString(),
            'status' => TripStatus::Planned,
            'created_by' => User::factory(),
        ];
    }
}
