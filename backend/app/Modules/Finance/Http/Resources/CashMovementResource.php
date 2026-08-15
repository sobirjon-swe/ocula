<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Resources;

use App\Modules\Finance\Models\CashMovement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Kassa harakati — SCHEMA.md §8.
 *
 * `signed_amount` ataylab alohida: kassa daftarini ko'rsatadigan ekran
 * ishorani o'zi hisoblab yurmasin.
 *
 * @mixin CashMovement
 */
class CashMovementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'branch_id' => $this->branch_id,
            'shift_id' => $this->shift_id,
            'type' => $this->type->value,
            'category' => $this->category->value,
            'amount' => $this->amount->toString(),
            'amount_formatted' => $this->amount->format(),
            'signed_amount' => $this->signedAmount()->toString(),
            'source_type' => $this->source_type,
            'source_id' => $this->source_id,
            'reverses_id' => $this->reverses_id,
            'reason' => $this->reason,
            'description' => $this->description,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
