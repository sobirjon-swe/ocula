<?php

declare(strict_types=1);

namespace App\Modules\Warehouse\Http\Requests\Purchase;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Kirim hujjatini yaratish — SCHEMA.md §3.
 *
 * Satrlar shu yerda birga yuboriladi: bo'sh hujjat yaratib qoldirish
 * amalda faqat chalkashlik keltiradi.
 *
 * `number` so'rovdan olinmaydi — u `DocumentNumber` orqali filial
 * kodidan hosil bo'ladi (ANALIZ 3.12), aks holda ikki xodim bir xil
 * raqam yozib yuborardi.
 */
class StorePurchaseRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'location_id' => ['required', 'integer', 'exists:locations,id'],
            'date' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:2000'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'items.*.cost_price' => ['required', 'numeric', 'min:0', 'max:9999999999999'],
        ];
    }
}
