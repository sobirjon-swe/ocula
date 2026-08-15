<?php

declare(strict_types=1);

namespace App\Modules\Sales\Http\Resources;

use App\Modules\Sales\Models\OrderReturn;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Qaytarish hujjati — SCHEMA.md §4 (3.6).
 *
 * @mixin OrderReturn
 */
class OrderReturnResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'order_id' => $this->order_id,
            'branch_id' => $this->branch_id,
            'shift_id' => $this->shift_id,
            'reason' => $this->reason->value,
            'amount' => $this->amount->toString(),
            'amount_formatted' => $this->amount->format(),
            'cost_total' => $this->when(
                $request->user()?->can('catalog.cost.view') ?? false,
                fn (): string => $this->cost_total->toString(),
            ),
            'approved_by' => $this->approved_by,
            'created_by' => $this->created_by,
            'items' => OrderReturnItemResource::collection($this->whenLoaded('items')),
            'items_count' => $this->whenCounted('items'),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
