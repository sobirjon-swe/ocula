<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Requests\Product;

use App\Modules\Catalog\Enums\ProductType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Tovarni tahrirlash.
 *
 * `status` va `merged_into_id` bu yerda o'zgarmaydi — ular uchun
 * alohida endpoint va alohida ruxsat bor (7.17).
 */
class UpdateProductRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'type' => ['sometimes', 'required', Rule::enum(ProductType::class)],
            'name' => ['sometimes', 'required', 'string', 'max:200'],
            'brand_id' => ['nullable', 'integer', 'exists:brands,id'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'unit' => ['nullable', 'string', 'max:16'],
            'is_active' => ['boolean'],
        ];
    }
}
