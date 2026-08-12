<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Catalog\Models\Price;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Core\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Price>
 */
class PriceFactory extends Factory
{
    protected $model = Price::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'variant_id' => ProductVariant::factory(),
            'branch_id' => null,
            'price' => '250000.00',
            'valid_from' => now()->subDay(),
            'valid_to' => null,
            'created_by' => User::factory(),
            'created_at' => now(),
        ];
    }
}
