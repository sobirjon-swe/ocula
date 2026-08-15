<?php

declare(strict_types=1);

namespace App\Modules\Warehouse\Http\Resources;

use App\Modules\Catalog\Http\Resources\ProductVariantResource;
use App\Modules\Warehouse\Models\TransferItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Transfer satri — SCHEMA.md §3.
 *
 * `qty_received` `null` bo'lishi "hali sanalmagan" degani; `missing`
 * esa qabul qilingandan keyin ma'noga ega bo'ladi.
 *
 * @mixin TransferItem
 */
class TransferItemResource extends JsonResource
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
            'qty_sent' => $this->qty_sent,
            'qty_received' => $this->qty_received,
            'missing' => $this->missingQuantity(),
            'unit_cost' => $this->when(
                $request->user()?->can('catalog.cost.view') ?? false,
                fn (): ?string => $this->unit_cost?->toString(),
            ),
        ];
    }
}
