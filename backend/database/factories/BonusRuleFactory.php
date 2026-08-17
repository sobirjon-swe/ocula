<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Core\Enums\Role;
use App\Modules\Core\Models\User;
use App\Modules\Payroll\Enums\BonusBase;
use App\Modules\Payroll\Models\BonusRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BonusRule>
 */
class BonusRuleFactory extends Factory
{
    protected $model = BonusRule::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'role' => Role::Seller->value,
            'base' => BonusBase::Revenue->value,
            'percent' => '5.00',
            'valid_from' => now()->subMonth()->toDateString(),
            'created_by' => User::factory(),
        ];
    }

    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes): array => [
            'user_id' => $user->id,
            'role' => null,
        ]);
    }
}
