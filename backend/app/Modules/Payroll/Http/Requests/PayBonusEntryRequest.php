<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * `bonus_entries` da `branch_id` yo'q — to'lov qaysi filial kassasidan
 * chiqishini xodim ko'rsatadi.
 */
class PayBonusEntryRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
        ];
    }
}
