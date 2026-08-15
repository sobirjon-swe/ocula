<?php

declare(strict_types=1);

namespace App\Modules\Sales\Http\Requests\Payment;

use Illuminate\Foundation\Http\FormRequest;

/**
 * To'lov stornosi — PROJECT.md 7.21.
 *
 * Izoh majburiy: pulni orqaga qaytargan yozuv sababsiz qolsa, keyin
 * uni hech kim tushuntira olmaydi.
 */
class ReversePaymentRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:3', 'max:255'],
        ];
    }
}
