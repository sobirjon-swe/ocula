<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Core\Models\Branch;
use App\Modules\Core\Models\User;
use App\Modules\Payroll\Enums\PlanType;
use App\Modules\Payroll\Models\BranchPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BranchPlan>
 */
class BranchPlanFactory extends Factory
{
    protected $model = BranchPlan::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'period' => now()->format('Y-m'),
            'type' => PlanType::Revenue->value,
            'target_amount' => '10000000.00',
            'created_by' => User::factory(),
        ];
    }
}
