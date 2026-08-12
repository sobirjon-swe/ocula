<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Requests\Brand;

use App\Modules\Catalog\Models\Brand;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Brend — SCHEMA.md §2.
 *
 * Nom noyob: dublikat brend katalogni ikkiga bo'lib yuboradi va
 * analitika brend kesimida yolg'on ko'rsatadi.
 */
class BrandRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $route = $this->route('brand');
        $id = $route instanceof Brand ? $route->getKey() : $route;

        return [
            'name' => [
                $this->isMethod('POST') ? 'required' : 'sometimes',
                'string',
                'max:120',
                Rule::unique('brands', 'name')->ignore($id),
            ],
            'is_active' => ['boolean'],
        ];
    }
}
