<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Http\Resources;

use App\Modules\Payroll\Models\BranchPlan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BranchPlan
 */
class BranchPlanResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'branch_id' => $this->branch_id,
            'period' => $this->period,
            'type' => $this->type->value,
            'target_amount' => $this->target_amount->toString(),
            'created_by' => $this->created_by,
        ];
    }
}
