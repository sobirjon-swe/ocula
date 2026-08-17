<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Requests\Expense;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Qo'lda xarajat yozish — SCHEMA.md `expenses`.
 *
 * `source_type`/`source_id` bu yerda so'ralmaydi — ular faqat avtomatik
 * yaratilgan xarajatlarda (taksi) bo'ladi, qo'lda yozilganda `null`.
 */
class StoreExpenseRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'category_id' => ['required', 'integer', 'exists:expense_categories,id'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999999'],
            'date' => ['nullable', 'date'],
            'description' => ['nullable', 'string', 'max:255'],
            'receipt_path' => ['nullable', 'string', 'max:255'],
        ];
    }
}
