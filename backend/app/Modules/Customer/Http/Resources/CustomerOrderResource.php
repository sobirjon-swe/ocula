<?php

declare(strict_types=1);

namespace App\Modules\Customer\Http\Resources;

use App\Modules\Sales\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Mijozning o'z buyurtmasi — PROJECT.md §6.9, 7.3, PERMISSIONS.md §12.
 *
 * Tannarx (`cost_total`) va ichki ustunlar (`shift_id`, `created_by`)
 * ataylab yo'q — bular xodim ishi. Satr-satr breakdown ham hozircha
 * yo'q (BOSQICH-9.md §1) — buyurtma darajasidagi summalar yetarli.
 *
 * @mixin Order
 */
class CustomerOrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'status' => $this->status->value,
            'payment_status' => $this->payment_status->value,
            'total' => $this->total->toString(),
            'paid' => $this->paid->toString(),
            'debt' => $this->debt->toString(),
            'due_date' => $this->due_date?->toDateString(),
            'delivery_type' => $this->delivery_type->value,
            'delivered_at' => $this->delivered_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
