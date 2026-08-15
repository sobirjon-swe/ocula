<?php

declare(strict_types=1);

namespace App\Modules\Clinic\Http\Resources;

use App\Modules\Clinic\Models\Visit;
use App\Modules\Sales\Http\Resources\CustomerResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Vizit — SCHEMA.md §5.
 *
 * @mixin Visit
 */
class VisitResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'branch_id' => $this->branch_id,
            'customer_id' => $this->customer_id,
            'customer' => new CustomerResource($this->whenLoaded('customer')),
            'doctor_id' => $this->doctor_id,
            'queue_number' => $this->queue_number,
            'queue_date' => $this->queue_date?->toDateString(),
            'status' => $this->status->value,
            'source' => $this->source->value,
            'started_at' => $this->started_at?->toIso8601String(),
            'finished_at' => $this->finished_at?->toIso8601String(),
            'prescription_id' => $this->whenLoaded('prescription', fn () => $this->prescription?->id),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
