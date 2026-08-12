<?php

declare(strict_types=1);

namespace App\Modules\Core\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Qarz limiti — PROJECT.md 7.6, §15 #3.
 *
 * `0` = bu xodim umuman qarzga bera olmaydi. Limit har bir sotuvchiga
 * alohida qo'yiladi, shuning uchun alohida endpoint va alohida ruxsat.
 */
class SetDebtLimitRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'debt_limit' => ['required', 'numeric', 'min:0', 'max:9999999999999'],
        ];
    }
}
