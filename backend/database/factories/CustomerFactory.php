<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Sales\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'phone' => '+9989'.fake()->unique()->numerify('########'),
            'locale' => 'uz-latn',
            'first_visit_at' => now(),
        ];
    }
}
