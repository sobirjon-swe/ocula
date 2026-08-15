<?php

declare(strict_types=1);

namespace App\Modules\Warehouse\Http\Resources;

use App\Modules\Warehouse\Models\Defect;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Brak — SCHEMA.md §3, PROJECT.md 7.5.
 *
 * `cost_impact` tannarx ma'lumoti, shuning uchun `catalog.cost.view`
 * ostida: sotuvchida bu ruxsat ataylab yo'q.
 *
 * @mixin Defect
 */
class DefectResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'branch_id' => $this->branch_id,
            'location_id' => $this->location_id,
            'variant_id' => $this->variant_id,
            'quantity' => $this->quantity,
            'reason' => $this->reason->value,
            'order_id' => $this->order_id,
            'work_order_id' => $this->work_order_id,
            'transfer_id' => $this->transfer_id,
            'cost_impact' => $this->when(
                $request->user()?->can('catalog.cost.view') ?? false,
                fn (): string => $this->cost_impact->toString(),
            ),
            'movement_id' => $this->movement_id,
            'photo_path' => $this->photo_path,
            'note' => $this->note,
            'reported_by' => $this->reported_by,
            'approved_by' => $this->approved_by,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
