<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Core\Enums\BranchType;
use App\Modules\Core\Models\Branch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Branch>
 */
class BranchFactory extends Factory
{
    protected $model = Branch::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
            'code' => strtoupper(fake()->unique()->lexify('??')),
            'type' => BranchType::Shop,
            'address' => fake()->address(),
            'open_time' => '10:00',
            'close_time' => '22:00',
            'is_active' => true,
        ];
    }

    public function main(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => BranchType::Main,
            'open_time' => '09:00',
        ]);
    }
}
