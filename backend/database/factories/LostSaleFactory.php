<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Core\Models\Branch;
use App\Modules\Core\Models\User;
use App\Modules\Warehouse\Enums\LostSaleReason;
use App\Modules\Warehouse\Models\LostSale;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LostSale>
 */
class LostSaleFactory extends Factory
{
    protected $model = LostSale::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'search_term' => fake()->words(2, true),
            'reason' => LostSaleReason::NotInCatalog->value,
            'created_by' => User::factory(),
        ];
    }
}
