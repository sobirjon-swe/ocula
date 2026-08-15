<?php

declare(strict_types=1);

namespace App\Modules\Sales\Http\Resources;

use App\Modules\Sales\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * To'lov — SCHEMA.md §4.
 *
 * `amount` ishorali: qaytarish va storno manfiy chiqadi.
 *
 * @mixin Payment
 */
class PaymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'customer_id' => $this->customer_id,
            'branch_id' => $this->branch_id,
            'shift_id' => $this->shift_id,
            'amount' => $this->amount->toString(),
            'amount_formatted' => $this->amount->format(),
            'method' => $this->method->value,
            'status' => $this->status->value,
            'reverses_id' => $this->reverses_id,
            'reason' => $this->reason,
            'received_by' => $this->received_by,
            'paid_at' => $this->paid_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
