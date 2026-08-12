<?php

declare(strict_types=1);

namespace App\Modules\Warehouse\Http\Resources;

use App\Modules\Catalog\Http\Resources\ProductVariantResource;
use App\Modules\Warehouse\Models\StockBalance;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Qoldiq — PROJECT.md 7.1.
 *
 * Bu kesh qiymati; haqiqat manbai `stock_movements`.
 *
 * @mixin StockBalance
 */
class StockBalanceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'location_id' => $this->location_id,
            'variant_id' => $this->variant_id,
            'branch_id' => $this->branch_id,
            'quantity' => $this->quantity,
            'variant' => new ProductVariantResource($this->whenLoaded('variant')),
        ];
    }
}
