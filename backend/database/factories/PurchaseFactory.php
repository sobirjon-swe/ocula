<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Core\Models\Branch;
use App\Modules\Core\Models\Location;
use App\Modules\Core\Models\User;
use App\Modules\Warehouse\Enums\PurchaseStatus;
use App\Modules\Warehouse\Models\Purchase;
use App\Modules\Warehouse\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Purchase>
 */
class PurchaseFactory extends Factory
{
    protected $model = Purchase::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $branch = Branch::factory();

        return [
            'supplier_id' => Supplier::factory(),
            'branch_id' => $branch,
            'location_id' => Location::factory()->for($branch),
            'number' => 'K-'.fake()->unique()->numerify('2608-#####'),
            'date' => now()->toDateString(),
            'total' => '0.00',
            'status' => PurchaseStatus::Draft,
            'created_by' => User::factory(),
        ];
    }

    public function received(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => PurchaseStatus::Received,
            'received_at' => now(),
        ]);
    }
}
