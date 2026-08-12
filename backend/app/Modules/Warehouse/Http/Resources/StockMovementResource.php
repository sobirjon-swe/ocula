<?php

declare(strict_types=1);

namespace App\Modules\Warehouse\Http\Resources;

use App\Modules\Catalog\Http\Resources\ProductVariantResource;
use App\Modules\Warehouse\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Ombor harakati — PROJECT.md 7.1, 7.20.
 *
 * Tannarx faqat `catalog.cost.view` bo'lganlarga chiqadi: sotuvchi
 * tannarxni ko'rsa, chegirma berishda unga qarab savdolashadi
 * (PERMISSIONS.md, nozikliklar #1).
 *
 * @mixin StockMovement
 */
class StockMovementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $canSeeCost = $request->user()?->can('catalog.cost.view') ?? false;

        return [
            'id' => $this->id,
            'branch_id' => $this->branch_id,
            'location_id' => $this->location_id,
            'variant_id' => $this->variant_id,
            'variant' => new ProductVariantResource($this->whenLoaded('variant')),
            'type' => $this->type->value,
            'quantity' => $this->quantity,
            'cost_total' => $this->when($canSeeCost, fn (): string => $this->cost_total->toString()),
            'cost_incomplete' => $this->when($canSeeCost, fn (): bool => $this->cost_incomplete),
            'source_type' => $this->source_type,
            'source_id' => $this->source_id,
            'reverses_id' => $this->reverses_id,
            'reason' => $this->reason,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
