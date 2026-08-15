<?php

declare(strict_types=1);

namespace App\Modules\Warehouse\Http\Requests\MasterStock;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Ombordan usta zaxirasiga berish — PROJECT.md §6.6, ANALIZ 3.2.
 *
 * Tovar filialdan chiqmaydi, faqat joyi o'zgaradi: ombordan ustaning
 * qo'liga. Shundan keyin "kimda nima bor" degan savolga daftar javob
 * beradi.
 */
class IssueToMasterRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'master_id' => ['required', 'integer', 'exists:users,id'],
            'location_id' => ['required', 'integer', 'exists:locations,id'],
            'variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
        ];
    }
}
