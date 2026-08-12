<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Requests\Category;

use App\Modules\Catalog\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Kategoriya — SCHEMA.md §2.
 *
 * `(parent_id, name)` juftligi noyob: bitta ota ostida bir xil nomli
 * ikki kategoriya bo'lmaydi, lekin turli otalar ostida bo'lishi mumkin
 * ("Erkaklar → Quyoshdan", "Ayollar → Quyoshdan").
 */
class CategoryRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $route = $this->route('category');
        $id = $route instanceof Category ? $route->getKey() : $route;

        return [
            'name' => [$this->isMethod('POST') ? 'required' : 'sometimes', 'string', 'max:120'],
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('categories', 'id'),
                // O'zini ota qilib qo'yish daraxtni cheksiz siklga soladi.
                Rule::notIn($id === null ? [] : [$id]),
            ],
            'sort' => ['integer', 'min:0', 'max:9999'],
            'is_active' => ['boolean'],
        ];
    }
}
