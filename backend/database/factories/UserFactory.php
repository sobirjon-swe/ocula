<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Core\Models\Branch;
use App\Modules\Core\Models\User;
use App\Support\Enums\Locale;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    protected static ?string $password = null;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            // Login telefon bo'yicha (SCHEMA.md §1) — email ixtiyoriy.
            'phone' => '+9989'.fake()->unique()->numerify('########'),
            'email' => null,
            'password' => static::$password ??= Hash::make('password'),
            'branch_id' => null,
            'debt_limit' => '0.00',
            'locale' => Locale::UzLatn,
            'is_active' => true,
            'remember_token' => Str::random(10),
        ];
    }

    public function forBranch(Branch $branch): static
    {
        return $this->state(fn (array $attributes): array => [
            'branch_id' => $branch->id,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }
}
