<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Core\Models\Branch;
use App\Modules\Finance\Enums\DebtStatus;
use App\Modules\Finance\Models\Debt;
use App\Modules\Sales\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Debt>
 */
class DebtFactory extends Factory
{
    protected $model = Debt::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $amount = (string) fake()->randomFloat(2, 50_000, 1_000_000);

        return [
            'customer_id' => Customer::factory(),
            'branch_id' => Branch::factory(),
            'amount' => $amount,
            'paid' => '0',
            'due_date' => fake()->dateTimeBetween('-10 days', '+10 days')->format('Y-m-d'),
            'status' => DebtStatus::Open->value,
        ];
    }
}
