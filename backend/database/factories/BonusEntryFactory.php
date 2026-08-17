<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Core\Models\User;
use App\Modules\Payroll\Enums\BonusStatus;
use App\Modules\Payroll\Models\BonusEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BonusEntry>
 */
class BonusEntryFactory extends Factory
{
    protected $model = BonusEntry::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'period' => now()->format('Y-m'),
            'base_amount' => '100000.00',
            'percent' => '5.00',
            'amount' => '5000.00',
            'status' => BonusStatus::Accrued->value,
            'calculated_at' => now(),
        ];
    }
}
