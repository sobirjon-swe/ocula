<?php

declare(strict_types=1);

namespace App\Modules\Sales\Http\Resources;

use App\Modules\Sales\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Buyurtma yoki chek — SCHEMA.md §4.
 *
 * Ikkala holat o'qi ham alohida chiqadi (`status` va `payment_status`)
 * — ekran ularni bir-biriga aylantirib yubormasin (7.3).
 *
 * @mixin Order
 */
class OrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'branch_id' => $this->branch_id,
            'customer_id' => $this->customer_id,
            'customer' => new CustomerResource($this->whenLoaded('customer')),
            'shift_id' => $this->shift_id,
            'type' => $this->type->value,
            'status' => $this->status->value,
            'payment_status' => $this->payment_status->value,
            'prescription_id' => $this->prescription_id,

            'subtotal' => $this->subtotal->toString(),
            'discount' => $this->discount->toString(),
            'rounding' => $this->rounding->toString(),
            'total' => $this->total->toString(),
            'total_formatted' => $this->total->format(),
            'paid' => $this->paid->toString(),
            'debt' => $this->debt->toString(),
            'cost_total' => $this->when(
                $request->user()?->can('catalog.cost.view') ?? false,
                fn (): string => $this->cost_total->toString(),
            ),

            'due_date' => $this->due_date?->toDateString(),
            'delivery_type' => $this->delivery_type->value,
            'allowed_next_statuses' => array_map(
                static fn ($status): string => $status->value,
                $this->status->allowedNext(),
            ),

            'status_changed_at' => $this->status_changed_at?->toIso8601String(),
            'revenue_recognized_at' => $this->revenue_recognized_at?->toIso8601String(),
            'delivered_at' => $this->delivered_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'discount_approved_by' => $this->discount_approved_by,

            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'items_count' => $this->whenCounted('items'),
            'payments' => PaymentResource::collection($this->whenLoaded('payments')),
            'created_by' => $this->created_by,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
