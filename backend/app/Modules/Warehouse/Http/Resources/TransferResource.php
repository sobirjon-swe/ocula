<?php

declare(strict_types=1);

namespace App\Modules\Warehouse\Http\Resources;

use App\Modules\Warehouse\Models\Transfer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Transfer — SCHEMA.md §3, PROJECT.md 7.4, 7.10.
 *
 * `has_discrepancy` alohida chiqadi: bu direktor ekranidagi qizil
 * signal, holatning o'zidan ko'ra tezroq ko'zga tashlanishi kerak.
 *
 * @mixin Transfer
 */
class TransferResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'from_location_id' => $this->from_location_id,
            'to_location_id' => $this->to_location_id,
            'transit_location_id' => $this->transit_location_id,
            'status' => $this->status->value,
            'delivery_method' => $this->delivery_method->value,
            'driver_id' => $this->driver_id,
            'carrier_user_id' => $this->carrier_user_id,
            'taxi_cost' => $this->taxi_cost?->toString(),
            'taxi_receipt_path' => $this->taxi_receipt_path,
            'sent_by' => $this->sent_by,
            'sent_at' => $this->sent_at?->toIso8601String(),
            'received_by' => $this->received_by,
            'received_at' => $this->received_at?->toIso8601String(),
            'has_discrepancy' => $this->has_discrepancy,
            'note' => $this->note,
            'items' => TransferItemResource::collection($this->whenLoaded('items')),
            'items_count' => $this->whenCounted('items'),
            'created_by' => $this->created_by,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
