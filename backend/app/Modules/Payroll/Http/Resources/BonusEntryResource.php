<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Http\Resources;

use App\Modules\Payroll\Models\BonusEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BonusEntry
 */
class BonusEntryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'order_id' => $this->order_id,
            'rule_id' => $this->rule_id,
            'period' => $this->period,
            'base_amount' => $this->base_amount->toString(),
            'percent' => (string) $this->percent,
            'amount' => $this->amount->toString(),
            'amount_formatted' => $this->amount->format(),
            'status' => $this->status->value,
            'reverses_id' => $this->reverses_id,
            'calculated_at' => $this->calculated_at->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
