<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Resources;

use App\Modules\Finance\Models\DebtReminder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin DebtReminder
 */
class DebtReminderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'debt_id' => $this->debt_id,
            'sent_at' => $this->sent_at->toIso8601String(),
            'channel' => $this->channel->value,
            'response' => $this->response->value,
            'responded_at' => $this->responded_at?->toIso8601String(),
            'note' => $this->note,
            'created_by' => $this->created_by,
        ];
    }
}
