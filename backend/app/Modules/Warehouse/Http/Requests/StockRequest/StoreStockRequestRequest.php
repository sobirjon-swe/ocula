<?php

declare(strict_types=1);

namespace App\Modules\Warehouse\Http\Requests\StockRequest;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Ichki so'rov — SCHEMA.md §3.
 *
 * `from_branch_id` so'rovdan olinmaydi: u so'rayotgan xodimning
 * filiali (kontrollerda qo'yiladi). Aks holda boshqa filial nomidan
 * so'rov yozib yuborish mumkin bo'lardi.
 */
class StoreStockRequestRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'to_branch_id' => ['required', 'integer', 'exists:branches,id'],
            'variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'order_id' => ['nullable', 'integer', 'exists:orders,id'],
        ];
    }
}
