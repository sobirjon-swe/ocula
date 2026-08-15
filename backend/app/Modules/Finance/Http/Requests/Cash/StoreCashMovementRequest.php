<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Requests\Cash;

use App\Modules\Finance\Enums\CashCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Qo'lda kassa yozuvi — SCHEMA.md §8.
 *
 * Toifa ro'yxati `CashCategory::manual()` bilan cheklangan: `sale`,
 * `refund`, `shift_opening` kabi toifalar o'z amalidan avtomatik
 * tug'iladi, ularni qo'lda yozish daftarni hujjatdan ajratib qo'yardi.
 *
 * `type` so'ralmaydi — yo'nalishni toifaning o'zi belgilaydi.
 */
class StoreCashMovementRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'category' => [
                'required',
                Rule::enum(CashCategory::class)->only(CashCategory::manual()),
            ],
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999999'],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }
}
