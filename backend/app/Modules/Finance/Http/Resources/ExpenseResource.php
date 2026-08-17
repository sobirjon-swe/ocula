<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Resources;

use App\Modules\Finance\Models\Expense;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Expense
 */
class ExpenseResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'branch_id' => $this->branch_id,
            'category' => new ExpenseCategoryResource($this->whenLoaded('category')),
            'amount' => $this->amount->toString(),
            'amount_formatted' => $this->amount->format(),
            'date' => $this->date->toDateString(),
            'description' => $this->description,
            'source_type' => $this->source_type,
            'source_id' => $this->source_id,
            'receipt_path' => $this->receipt_path,
            'approved_by' => $this->approved_by,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
