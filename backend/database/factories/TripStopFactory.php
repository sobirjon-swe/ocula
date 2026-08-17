<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Delivery\Enums\TripStopStatus;
use App\Modules\Delivery\Enums\TripStopType;
use App\Modules\Delivery\Models\Trip;
use App\Modules\Delivery\Models\TripStop;
use App\Modules\Sales\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TripStop>
 */
class TripStopFactory extends Factory
{
    protected $model = TripStop::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'trip_id' => Trip::factory(),
            'sequence' => 1,
            'type' => TripStopType::Customer,
            'customer_id' => Customer::factory(),
            'address' => fake()->address(),
            'status' => TripStopStatus::Pending,
        ];
    }
}
