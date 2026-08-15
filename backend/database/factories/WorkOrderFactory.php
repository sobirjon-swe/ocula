<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Core\Models\Branch;
use App\Modules\Core\Models\User;
use App\Modules\Sales\Models\Order;
use App\Modules\Workshop\Enums\WorkOrderPriority;
use App\Modules\Workshop\Enums\WorkOrderStatus;
use App\Modules\Workshop\Models\WorkOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkOrder>
 */
class WorkOrderFactory extends Factory
{
    protected $model = WorkOrder::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'branch_id' => Branch::factory(),
            'status' => WorkOrderStatus::Queued,
            'priority' => WorkOrderPriority::Normal,
            'created_by' => User::factory(),
        ];
    }

    public function inProgress(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => WorkOrderStatus::InProgress,
            'started_at' => now(),
        ]);
    }
}
