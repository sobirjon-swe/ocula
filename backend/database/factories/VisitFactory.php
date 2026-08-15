<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Clinic\Enums\VisitSource;
use App\Modules\Clinic\Enums\VisitStatus;
use App\Modules\Clinic\Models\Visit;
use App\Modules\Core\Models\Branch;
use App\Modules\Sales\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Visit>
 */
class VisitFactory extends Factory
{
    protected $model = Visit::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'customer_id' => Customer::factory(),
            'queue_number' => fake()->unique()->numberBetween(1, 30000),
            'queue_date' => now()->toDateString(),
            'status' => VisitStatus::Waiting,
            'source' => VisitSource::WalkIn,
        ];
    }

    public function inProgress(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => VisitStatus::InProgress,
            'started_at' => now(),
        ]);
    }
}
