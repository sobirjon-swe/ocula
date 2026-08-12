<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Catalog\Enums\ProductStatus;
use App\Modules\Catalog\Enums\ProductType;
use App\Modules\Catalog\Models\Product;
use App\Modules\Core\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => ProductType::Frame,
            'name' => fake()->unique()->words(2, true),
            'unit' => 'pcs',
            'status' => ProductStatus::Approved,
            'quick_created' => false,
            'created_by' => User::factory(),
            'is_active' => true,
        ];
    }

    /**
     * Sotuv paytida qo'shilgan tovar (7.13) — tasdiq kutmoqda, lekin sotiladi.
     */
    public function quickCreated(): static
    {
        return $this->state(fn (array $attributes): array => [
            'quick_created' => true,
            'status' => ProductStatus::Pending,
        ]);
    }

    public function lens(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => ProductType::Lens,
        ]);
    }
}
