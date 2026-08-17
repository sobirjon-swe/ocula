<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Core\Models\User;
use App\Modules\Finance\Enums\SupplierTxType;
use App\Modules\Finance\Models\SupplierTransaction;
use App\Modules\Warehouse\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupplierTransaction>
 */
class SupplierTransactionFactory extends Factory
{
    protected $model = SupplierTransaction::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'supplier_id' => Supplier::factory(),
            'type' => SupplierTxType::Payment->value,
            'amount' => (string) fake()->randomFloat(2, 50_000, 500_000),
            'created_by' => User::factory(),
        ];
    }
}
