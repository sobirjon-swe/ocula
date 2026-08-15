<?php

declare(strict_types=1);

namespace App\Modules\Warehouse\Http\Resources;

use App\Modules\Catalog\Http\Resources\ProductVariantResource;
use App\Modules\Warehouse\Models\StockRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Ichki so'rov — SCHEMA.md §3.
 *
 * @mixin StockRequest
 */
class StockRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'from_branch_id' => $this->from_branch_id,
            'to_branch_id' => $this->to_branch_id,
            'variant_id' => $this->variant_id,
            'variant' => new ProductVariantResource($this->whenLoaded('variant')),
            'quantity' => $this->quantity,
            'order_id' => $this->order_id,
            'status' => $this->status->value,
            'transfer_id' => $this->transfer_id,
            'requested_by' => $this->requested_by,
            'approved_by' => $this->approved_by,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
