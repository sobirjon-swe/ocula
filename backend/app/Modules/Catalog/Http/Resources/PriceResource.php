<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Resources;

use App\Modules\Catalog\Models\Price;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Narx yozuvi — SCHEMA.md §2, ANALIZ 3.16.
 *
 * `branch_id = null` — global narx; filial narxi undan ustun.
 *
 * @mixin Price
 */
class PriceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'variant_id' => $this->variant_id,
            'branch_id' => $this->branch_id,
            'price' => $this->price->toString(),
            'price_formatted' => $this->price->format(),
            'valid_from' => $this->valid_from?->toIso8601String(),
            'valid_to' => $this->valid_to?->toIso8601String(),
            'is_current' => $this->valid_to === null,
        ];
    }
}
