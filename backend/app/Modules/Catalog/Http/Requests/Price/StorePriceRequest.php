<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Requests\Price;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Narx o'rnatish — SCHEMA.md §2, ANALIZ 3.16.
 *
 * `branch_id = null` — global narx. Filial narxi global narxdan ustun,
 * shuning uchun ikkalasi bir vaqtda mavjud bo'lishi normal.
 */
class StorePriceRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'price' => ['required', 'numeric', 'min:0', 'max:9999999999999'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'valid_from' => ['nullable', 'date'],
        ];
    }
}
