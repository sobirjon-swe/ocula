<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Http\Resources;

use App\Modules\Payroll\Models\BonusRule;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BonusRule
 */
class BonusRuleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'role' => $this->role,
            'branch_id' => $this->branch_id,
            'base' => $this->base->value,
            'percent' => (string) $this->percent,
            'valid_from' => $this->valid_from->toDateString(),
            'valid_to' => $this->valid_to?->toDateString(),
            'created_by' => $this->created_by,
        ];
    }
}
