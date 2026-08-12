<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Resources;

use App\Modules\Catalog\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Tovar — SCHEMA.md §2, PROJECT.md 7.13, 7.17.
 *
 * `status = pending` tovar ham sotiladi va qoldiqda bor (7.17), shuning
 * uchun status javobda ochiq turadi — UI uni "tekshirilmagan" belgisi
 * bilan ko'rsatadi, lekin sotuvni to'smaydi.
 *
 * @mixin Product
 */
class ProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'name' => $this->name,
            'brand_id' => $this->brand_id,
            'brand' => $this->when(
                $this->relationLoaded('brand'),
                fn (): ?BrandResource => $this->brand === null ? null : new BrandResource($this->brand),
            ),
            'category_id' => $this->category_id,
            'category' => $this->when(
                $this->relationLoaded('category'),
                fn (): ?CategoryResource => $this->category === null
                    ? null
                    : new CategoryResource($this->category),
            ),
            'unit' => $this->unit,
            'status' => $this->status->value,
            'quick_created' => $this->quick_created,
            'merged_into_id' => $this->merged_into_id,
            'is_active' => $this->is_active,
            'approved_at' => $this->approved_at?->toIso8601String(),
            'variants' => ProductVariantResource::collection($this->whenLoaded('variants')),
            'variants_count' => $this->whenCounted('variants'),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
