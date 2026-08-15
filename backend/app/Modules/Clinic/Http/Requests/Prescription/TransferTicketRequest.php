<?php

declare(strict_types=1);

namespace App\Modules\Clinic\Http\Requests\Prescription;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Tiketni boshqa filialga o'tkazish — PROJECT.md 7.11.
 *
 * Sabab **majburiy**: bu 7.11 qoidasidan chekinish va u keyin
 * tushuntirilishi kerak — "nega B filialda A ning retsepti bo'yicha
 * ko'zoynak yasaldi".
 */
class TransferTicketRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'to_branch_id' => ['required', 'integer', 'exists:branches,id'],
            'reason' => ['required', 'string', 'min:3', 'max:255'],
        ];
    }
}
