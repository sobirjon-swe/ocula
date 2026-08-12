<?php

declare(strict_types=1);

namespace App\Modules\Core\Http\Resources;

use App\Modules\Core\Models\Shift;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Smena — PROJECT.md §6.1, §15 #24.
 *
 * @mixin Shift
 */
class ShiftResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'branch_id' => $this->branch_id,
            'branch' => $this->when(
                $this->relationLoaded('branch'),
                fn (): ?BranchResource => $this->branch === null
                    ? null
                    : new BranchResource($this->branch),
            ),
            'status' => $this->status->value,
            'opened_at' => $this->opened_at?->toIso8601String(),
            'closed_at' => $this->closed_at?->toIso8601String(),
            'opened_by' => new UserResource($this->whenLoaded('openedBy')),
            'closed_by' => $this->when(
                $this->relationLoaded('closedBy'),
                fn (): ?UserResource => $this->closedBy === null
                    ? null
                    : new UserResource($this->closedBy),
            ),
            'opening_cash' => $this->opening_cash->toString(),
            'expected_cash' => $this->expected_cash?->toString(),
            'actual_cash' => $this->actual_cash?->toString(),
            'difference' => $this->difference?->toString(),
            'has_shortage' => $this->hasShortage(),
            'note' => $this->note,
        ];
    }
}
