<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductVariant>
 */
class ProductVariantFactory extends Factory
{
    protected $model = ProductVariant::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'sku' => fake()->unique()->bothify('SKU-####??'),
            'attributes' => [],
            'is_active' => true,
        ];
    }

    /**
     * Linza kombinatsiyasi (7.2).
     */
    public function lens(string $sph, string $cyl, int $axis): static
    {
        return $this->state(fn (array $attributes): array => [
            'sph' => $sph,
            'cyl' => $cyl,
            'axis' => $axis,
            'index' => '1.61',
            'coating' => 'hmc',
        ]);
    }
}
