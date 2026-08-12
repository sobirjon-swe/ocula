<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Resources;

use App\Modules\Catalog\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Tovar varianti — SCHEMA.md §2, PROJECT.md 7.2.
 *
 * `optical_label` retseptga solishtirish uchun tayyor matn beradi:
 * `SPH −2.25 · CYL −0.75 · AXIS 90`.
 *
 * @mixin ProductVariant
 */
class ProductVariantResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'sku' => $this->sku,
            'barcode' => $this->barcode,
            'attributes' => $this->getAttribute('attributes'),
            'sph' => $this->sph,
            'cyl' => $this->cyl,
            'axis' => $this->axis,
            'add' => $this->add,
            'index' => $this->index,
            'coating' => $this->coating?->value,
            'diameter' => $this->diameter,
            'color' => $this->color,
            'size' => $this->size,
            'optical_label' => $this->opticalLabel(),
            'is_active' => $this->is_active,
        ];
    }
}
