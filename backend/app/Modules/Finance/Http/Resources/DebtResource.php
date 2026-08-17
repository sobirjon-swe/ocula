<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Resources;

use App\Modules\Finance\Models\Debt;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Debt
 */
class DebtResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'customer_id' => $this->customer_id,
            'order_id' => $this->order_id,
            'branch_id' => $this->branch_id,
            'amount' => $this->amount->toString(),
            'paid' => $this->paid->toString(),
            'remaining' => $this->remaining()->toString(),
            'remaining_formatted' => $this->remaining()->format(),
            'due_date' => $this->due_date->toDateString(),
            'status' => $this->status->value,
            'write_off_reason' => $this->write_off_reason,
            'closed_at' => $this->closed_at?->toIso8601String(),
            'approved_by' => $this->approved_by,
            'created_at' => $this->created_at?->toIso8601String(),
            'reminders' => DebtReminderResource::collection($this->whenLoaded('reminders')),
        ];
    }
}
