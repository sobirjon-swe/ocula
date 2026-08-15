<?php

declare(strict_types=1);

namespace App\Modules\Sales\Http\Resources;

use App\Modules\Sales\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Mijoz — SCHEMA.md §4.
 *
 * Qarz balansi alohida ruxsat ostida (`sales.customer.debt.view`):
 * har bir xodim mijozning qarzini bilishi shart emas.
 *
 * @mixin Customer
 */
class CustomerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'birth_date' => $this->birth_date?->toDateString(),
            'telegram_id' => $this->telegram_id,
            'locale' => $this->locale->value,
            'notes' => $this->notes,
            'debt_balance' => $this->when(
                $request->user()?->can('sales.customer.debt.view') ?? false,
                fn (): string => $this->debt_balance->toString(),
            ),
            'first_visit_at' => $this->first_visit_at?->toIso8601String(),
            'abandoned_orders_count' => $this->abandoned_orders_count,
            'branch_id' => $this->branch_id,
            'orders_count' => $this->whenCounted('orders'),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
