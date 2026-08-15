<?php

declare(strict_types=1);

namespace App\Modules\Sales\Http\Requests\Payment;

use App\Modules\Sales\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * To'lov qabul qilish — SCHEMA.md §4, ENUMS.md §4.
 *
 * `status` so'ralmaydi: muvaffaqiyatsiz to'lov umuman yozilmaydi,
 * qolganlari `completed` bo'ladi.
 */
class StorePaymentRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999999'],
            'method' => ['required', Rule::enum(PaymentMethod::class)],
        ];
    }
}
