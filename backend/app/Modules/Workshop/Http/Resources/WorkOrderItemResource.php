<?php

declare(strict_types=1);

namespace App\Modules\Workshop\Http\Resources;

use App\Modules\Catalog\Http\Resources\ProductVariantResource;
use App\Modules\Workshop\Models\WorkOrderItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Ish buyrug'ining materiali — SCHEMA.md §6.
 *
 * `is_consumed` — material ombordan chiqqanmi. Usta ekranida bu
 * "linza qo'limda" degan ma'noni beradi.
 *
 * @mixin WorkOrderItem
 */
class WorkOrderItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'variant_id' => $this->variant_id,
            'variant' => new ProductVariantResource($this->whenLoaded('variant')),
            'quantity' => $this->quantity,
            'is_consumed' => $this->isConsumed(),
            'consumed_movement_id' => $this->consumed_movement_id,
        ];
    }
}
