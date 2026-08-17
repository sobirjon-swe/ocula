<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Requests\Debt;

use Illuminate\Foundation\Http\FormRequest;

class WriteOffDebtRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:255'],
        ];
    }
}
