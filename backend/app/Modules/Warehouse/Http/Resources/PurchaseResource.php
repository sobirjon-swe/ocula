<?php

declare(strict_types=1);

namespace App\Modules\Warehouse\Http\Resources;

use App\Modules\Warehouse\Models\Purchase;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Kirim hujjati — SCHEMA.md §3.
 *
 * @mixin Purchase
 */
class PurchaseResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'supplier_id' => $this->supplier_id,
            'supplier' => new SupplierResource($this->whenLoaded('supplier')),
            'branch_id' => $this->branch_id,
            'location_id' => $this->location_id,
            'date' => $this->date?->toDateString(),
            'total' => $this->total->toString(),
            'total_formatted' => $this->total->format(),
            'status' => $this->status->value,
            'received_at' => $this->received_at?->toIso8601String(),
            'received_by' => $this->received_by,
            'note' => $this->note,
            'items' => PurchaseItemResource::collection($this->whenLoaded('items')),
            'items_count' => $this->whenCounted('items'),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
