<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Core\Models\Branch;
use App\Modules\Core\Models\User;
use App\Modules\Finance\Models\Expense;
use App\Modules\Finance\Models\ExpenseCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Expense>
 */
class ExpenseFactory extends Factory
{
    protected $model = Expense::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'category_id' => ExpenseCategory::factory(),
            'amount' => (string) fake()->randomFloat(2, 10_000, 500_000),
            'date' => fake()->dateTimeBetween('-1 month')->format('Y-m-d'),
            'description' => fake()->sentence(),
            'created_by' => User::factory(),
        ];
    }
}
