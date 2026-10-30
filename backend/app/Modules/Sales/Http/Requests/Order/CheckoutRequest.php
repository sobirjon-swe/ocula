<?php

declare(strict_types=1);

namespace App\Modules\Sales\Http\Requests\Order;

use App\Modules\Sales\Enums\PaymentMethod;
use Illuminate\Validation\Rule;

class CheckoutRequest extends StoreOrderRequest
{
    public function rules(): array
    {
        return [...parent::rules(),
            'type' => ['required', 'in:quick'],
            'payments' => ['required', 'array', 'min:1'],
            'payments.*.amount' => ['required', 'numeric', 'gt:0', 'max:9999999999999'],
            'payments.*.method' => ['required', Rule::enum(PaymentMethod::class)],
        ];
    }
}
