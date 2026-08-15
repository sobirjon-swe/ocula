<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Core\Models\Branch;
use App\Modules\Core\Models\User;
use App\Modules\Warehouse\Enums\StockRequestStatus;
use App\Modules\Warehouse\Models\StockRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockRequest>
 */
class StockRequestFactory extends Factory
{
    protected $model = StockRequest::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'from_branch_id' => Branch::factory(),
            'to_branch_id' => Branch::factory(),
            'variant_id' => ProductVariant::factory(),
            'quantity' => 2,
            'status' => StockRequestStatus::Pending,
            'requested_by' => User::factory(),
        ];
    }
}
