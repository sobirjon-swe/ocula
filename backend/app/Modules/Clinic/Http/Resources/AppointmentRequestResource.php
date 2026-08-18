<?php

declare(strict_types=1);

namespace App\Modules\Clinic\Http\Resources;

use App\Modules\Clinic\Models\AppointmentRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AppointmentRequest
 */
class AppointmentRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'branch_id' => $this->branch_id,
            'name' => $this->name,
            'phone' => $this->phone,
            'preferred_date' => $this->preferred_date?->toDateString(),
            'note' => $this->note,
            'status' => $this->status->value,
            'handled_by' => $this->handled_by,
            'handled_at' => $this->handled_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
