<?php

declare(strict_types=1);

namespace App\Modules\Workshop\Http\Resources;

use App\Modules\Workshop\Models\WorkOrder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Ish buyrug'i — SCHEMA.md §6.
 *
 * `is_overdue` ataylab hisoblab beriladi: kanbanda rang shu bo'yicha
 * qo'yiladi (§10 — kechikkan qizil), ekran sanani o'zi solishtirib
 * yurmasin.
 *
 * @mixin WorkOrder
 */
class WorkOrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'order_number' => $this->whenLoaded('order', fn () => $this->order?->number),
            'customer_name' => $this->whenLoaded('order', fn () => $this->order?->customer?->name),
            'prescription' => $this->whenLoaded('order', function (): ?array {
                $rx = $this->order?->prescription;

                return $rx === null ? null : [
                    'od' => ['sph' => $rx->od_sph, 'cyl' => $rx->od_cyl, 'axis' => $rx->od_axis, 'add' => $rx->od_add],
                    'os' => ['sph' => $rx->os_sph, 'cyl' => $rx->os_cyl, 'axis' => $rx->os_axis, 'add' => $rx->os_add],
                    'pd' => $rx->pd,
                ];
            }),
            'frame_label' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => $item->variant?->product?->name)->filter()->implode(' · ')),
            'branch_id' => $this->branch_id,
            'master_id' => $this->master_id,
            'status' => $this->status->value,
            'priority' => $this->priority->value,
            'due_at' => $this->due_at?->toIso8601String(),
            'is_overdue' => $this->isOverdue(),
            'started_at' => $this->started_at?->toIso8601String(),
            'finished_at' => $this->finished_at?->toIso8601String(),
            'rework_count' => $this->rework_count,
            'note' => $this->note,
            'items' => WorkOrderItemResource::collection($this->whenLoaded('items')),
            'items_count' => $this->whenCounted('items'),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
