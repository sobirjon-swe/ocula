<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Clinic\Models\AppointmentRequest;
use App\Modules\Core\Models\Branch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AppointmentRequest>
 */
class AppointmentRequestFactory extends Factory
{
    protected $model = AppointmentRequest::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'name' => fake()->name(),
            'phone' => '+998'.fake()->numerify('#########'),
        ];
    }
}
