<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Core\Models\Branch;
use App\Modules\Core\Models\User;
use App\Modules\Sales\Enums\OrderStatus;
use App\Modules\Sales\Enums\OrderType;
use App\Modules\Sales\Enums\PaymentStatus;
use App\Modules\Sales\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'number' => 'A-'.fake()->unique()->numerify('2608-#####'),
            'branch_id' => Branch::factory(),
            'type' => OrderType::Order,
            'status' => OrderStatus::New,
            'payment_status' => PaymentStatus::Unpaid,
            'created_by' => User::factory(),
        ];
    }

    public function quick(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => OrderType::Quick,
        ]);
    }

    public function delivered(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => OrderStatus::Delivered,
            'delivered_at' => now(),
            'revenue_recognized_at' => now(),
        ]);
    }
}
