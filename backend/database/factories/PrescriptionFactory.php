<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Clinic\Models\Prescription;
use App\Modules\Core\Models\Branch;
use App\Modules\Core\Models\User;
use App\Modules\Sales\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Prescription>
 */
class PrescriptionFactory extends Factory
{
    protected $model = Prescription::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $branch = Branch::factory();

        return [
            'customer_id' => Customer::factory(),
            'branch_id' => $branch,
            'doctor_id' => User::factory(),
            'od_sph' => '-1.25',
            'os_sph' => '-1.00',
            'pd' => '62.0',
            'valid_until' => now()->addYear()->toDateString(),
            'ticket_active' => true,
            'ticket_branch_id' => $branch,
        ];
    }

    /**
     * Muddati o'tgan retsept — buyurtmada ogohlantirish beradi (7.11).
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes): array => [
            'valid_until' => now()->subMonth()->toDateString(),
        ]);
    }

    public function ticketClosed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'ticket_active' => false,
        ]);
    }
}
