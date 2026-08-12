<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Warehouse\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Supplier>
 */
class SupplierFactory extends Factory
{
    protected $model = Supplier::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'phone' => '+9989'.fake()->numerify('########'),
            'payment_terms_days' => 0,
            'is_active' => true,
        ];
    }

    public function onCredit(int $days = 30): static
    {
        return $this->state(fn (array $attributes): array => [
            'payment_terms_days' => $days,
        ]);
    }
}
