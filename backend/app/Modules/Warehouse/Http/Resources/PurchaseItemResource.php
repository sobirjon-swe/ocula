<?php

declare(strict_types=1);

namespace App\Modules\Warehouse\Http\Resources;

use App\Modules\Catalog\Http\Resources\ProductVariantResource;
use App\Modules\Warehouse\Models\PurchaseItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Kirim satri — `cost_price` FIFO qatlamining `unit_cost` iga aylanadi.
 *
 * @mixin PurchaseItem
 */
class PurchaseItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'purchase_id' => $this->purchase_id,
            'variant_id' => $this->variant_id,
            'variant' => new ProductVariantResource($this->whenLoaded('variant')),
            'quantity' => $this->quantity,
            'cost_price' => $this->cost_price->toString(),
            'total' => $this->total->toString(),
        ];
    }
}
