<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Requests\ExpenseCategory;

use Illuminate\Foundation\Http\FormRequest;

class StoreExpenseCategoryRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120', 'unique:expense_categories,name'],
            'code' => ['required', 'string', 'max:32', 'alpha_dash', 'unique:expense_categories,code'],
            'is_active' => ['boolean'],
        ];
    }
}
