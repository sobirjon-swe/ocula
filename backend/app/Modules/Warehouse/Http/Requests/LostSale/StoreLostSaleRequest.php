<?php

declare(strict_types=1);

namespace App\Modules\Warehouse\Http\Requests\LostSale;

use App\Modules\Warehouse\Enums\LostSaleReason;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Yo'qotilgan savdo yozuvi — SCHEMA.md `lost_sales`, PROJECT.md 7.9.
 *
 * `variant_id` yoki `search_term` dan kamida bittasi shart (baza
 * `CHECK` cheklovi bilan bir xil qoida — xato shu yerdan qaytsin).
 */
class StoreLostSaleRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'search_term' => ['nullable', 'string', 'max:160'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'reason' => ['required', Rule::enum(LostSaleReason::class)],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->filled('variant_id') || $this->filled('search_term')) {
                return;
            }

            $validator->errors()->add('variant_id', __('warehouse::lost_sale.variant_or_term_required'));
        });
    }
}
