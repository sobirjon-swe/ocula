<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Core\Enums\ShiftStatus;
use App\Modules\Core\Models\Branch;
use App\Modules\Core\Models\Shift;
use App\Modules\Core\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Shift>
 */
class ShiftFactory extends Factory
{
    protected $model = Shift::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'opened_by' => User::factory(),
            'opened_at' => now(),
            'opening_cash' => '0.00',
            'status' => ShiftStatus::Open,
        ];
    }

    /**
     * @param  numeric-string  $expected
     * @param  numeric-string  $actual
     */
    public function closed(string $expected, string $actual): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ShiftStatus::Closed,
            'closed_at' => now(),
            'expected_cash' => $expected,
            'actual_cash' => $actual,
            'difference' => bcsub($actual, $expected, 2),
        ]);
    }
}
