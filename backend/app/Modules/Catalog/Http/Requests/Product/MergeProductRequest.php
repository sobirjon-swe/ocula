<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Requests\Product;

use App\Modules\Catalog\Models\Product;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Dublikatni asosiy tovarga birlashtirish — PROJECT.md 7.13, 7.17.
 *
 * Birlashtirilgan tovar o'chirilmaydi: `merged_into_id` orqali asosiyga
 * ishora qiladi, ombor harakati va sotuv tarixi joyida qoladi.
 */
class MergeProductRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'merge_into_id' => ['required', 'integer', 'exists:products,id', 'different:product'],
        ];
    }

    /**
     * `different:product` route parametridan qiymat oladi.
     */
    protected function prepareForValidation(): void
    {
        $route = $this->route('product');

        $this->merge([
            'product' => $route instanceof Product ? $route->getKey() : $route,
        ]);
    }
}
