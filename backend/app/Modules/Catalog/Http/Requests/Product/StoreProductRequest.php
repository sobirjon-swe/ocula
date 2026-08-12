<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Requests\Product;

use App\Modules\Catalog\Enums\ProductType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Tovar yaratish — PROJECT.md 7.13, 7.17.
 *
 * `status` bu yerda **qabul qilinmaydi**: u yaratish usuliga qarab
 * kontrollerda qo'yiladi (to'liq yaratish → `approved` huquqi bo'lsa,
 * tez qo'shish → doim `pending`). Aks holda sotuvchi so'rovga
 * `status=approved` yozib, tasdiqlashni chetlab o'tardi.
 */
class StoreProductRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(ProductType::class)],
            'name' => ['required', 'string', 'max:200'],
            'brand_id' => ['nullable', 'integer', 'exists:brands,id'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'unit' => ['nullable', 'string', 'max:16'],
        ];
    }
}
