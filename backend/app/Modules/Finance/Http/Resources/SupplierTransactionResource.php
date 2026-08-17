<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Resources;

use App\Modules\Finance\Models\SupplierTransaction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SupplierTransaction
 */
class SupplierTransactionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'supplier_id' => $this->supplier_id,
            'type' => $this->type->value,
            'amount' => $this->amount->toString(),
            'amount_formatted' => $this->amount->format(),
            'purchase_id' => $this->purchase_id,
            'due_date' => $this->due_date?->toDateString(),
            'reverses_id' => $this->reverses_id,
            'reason' => $this->reason,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
