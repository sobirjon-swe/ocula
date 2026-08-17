<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Http\Resources;

use App\Modules\Delivery\Models\CashCollection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Inkassatsiya — SCHEMA.md (`collections`), PROJECT.md §5.2.
 *
 * @mixin CashCollection
 */
class CashCollectionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'driver_id' => $this->driver_id,
            'branch_id' => $this->branch_id,
            'shift_id' => $this->shift_id,
            'amount' => $this->amount->toString(),
            'received_by' => $this->received_by,
            'note' => $this->note,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
