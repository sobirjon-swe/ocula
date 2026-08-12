<?php

declare(strict_types=1);

namespace App\Modules\Warehouse\Http\Requests\Supplier;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Yetkazib beruvchi — SCHEMA.md §3, PROJECT.md 7.15.
 */
class SupplierRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'name' => [$required, 'string', 'max:160'],
            'phone' => ['nullable', 'string', 'max:32'],
            'payment_terms_days' => ['integer', 'min:0', 'max:365'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['boolean'],
        ];
    }
}
