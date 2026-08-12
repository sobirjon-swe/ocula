<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Requests\Variant;

use App\Modules\Catalog\Enums\LensCoating;
use App\Modules\Catalog\Models\ProductVariant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Tovar varianti — SCHEMA.md §2, PROJECT.md 7.2.
 *
 * Optik qiymatlar chegarasi haqiqiy retsept diapazonidan olingan:
 * SPH/CYL ±30.00 dan tashqarisi — kiritishdagi xato.
 */
class VariantRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $route = $this->route('variant');
        $id = $route instanceof ProductVariant ? $route->getKey() : $route;

        return [
            'sku' => ['nullable', 'string', 'max:64', Rule::unique('product_variants', 'sku')->ignore($id)],
            'barcode' => ['nullable', 'string', 'max:64', Rule::unique('product_variants', 'barcode')->ignore($id)],
            'attributes' => ['array'],
            'sph' => ['nullable', 'numeric', 'between:-30,30'],
            'cyl' => ['nullable', 'numeric', 'between:-30,30'],
            'axis' => ['nullable', 'integer', 'between:0,180'],
            'add' => ['nullable', 'numeric', 'between:0,10'],
            'index' => ['nullable', 'numeric', 'between:1,2'],
            'coating' => ['nullable', Rule::enum(LensCoating::class)],
            'diameter' => ['nullable', 'integer', 'between:40,90'],
            'color' => ['nullable', 'string', 'max:40'],
            'size' => ['nullable', 'string', 'max:40'],
            'is_active' => ['boolean'],
        ];
    }
}
