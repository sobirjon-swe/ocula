<?php

declare(strict_types=1);

namespace App\Modules\Sales\Http\Resources;

use App\Modules\Sales\Models\OrderReturnItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Qaytarish satri — SCHEMA.md §4 (3.6).
 *
 * `restock` ko'rinib turishi kerak: omborga qaytgan tovar bilan brakka
 * ketgan tovar bir xil qatorda ko'rinsa, qoldiq tushunarsiz bo'lardi.
 *
 * @mixin OrderReturnItem
 */
class OrderReturnItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_item_id' => $this->order_item_id,
            'variant_id' => $this->variant_id,
            'quantity' => $this->quantity,
            'amount' => $this->amount->toString(),
            'cost_total' => $this->when(
                $request->user()?->can('catalog.cost.view') ?? false,
                fn (): string => $this->cost_total->toString(),
            ),
            'restock' => $this->restock,
            'movement_id' => $this->movement_id,
        ];
    }
}
