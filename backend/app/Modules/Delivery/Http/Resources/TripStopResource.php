<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Http\Resources;

use App\Modules\Delivery\Models\TripStop;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * To'xtash — SCHEMA.md, PROJECT.md 7.4.
 *
 * @mixin TripStop
 */
class TripStopResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'trip_id' => $this->trip_id,
            'sequence' => $this->sequence,
            'type' => $this->type->value,
            'branch_id' => $this->branch_id,
            'customer_id' => $this->customer_id,
            'order_id' => $this->order_id,
            'transfer_id' => $this->transfer_id,
            'address' => $this->address,
            'lat' => $this->lat,
            'lng' => $this->lng,
            'cash_to_collect' => $this->cash_to_collect->toString(),
            'status' => $this->status->value,
            'delivered_at' => $this->delivered_at?->toIso8601String(),
            'delivered_lat' => $this->delivered_lat,
            'delivered_lng' => $this->delivered_lng,
            'distance_m' => $this->distance_m,
            'photo_path' => $this->photo_path,
            'customer_confirmed_at' => $this->customer_confirmed_at?->toIso8601String(),
            'confirmation_status' => $this->confirmation_status?->value,
            'fail_reason' => $this->fail_reason,
        ];
    }
}
