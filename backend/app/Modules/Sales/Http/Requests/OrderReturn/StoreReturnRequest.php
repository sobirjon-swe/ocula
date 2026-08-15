<?php

declare(strict_types=1);

namespace App\Modules\Sales\Http\Requests\OrderReturn;

use App\Modules\Sales\Enums\PaymentMethod;
use App\Modules\Sales\Enums\ReturnReason;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Qaytarish — SCHEMA.md §4 (3.6).
 *
 * `refund` alohida maydon va tovar summasiga avtomatik teng emas:
 * mijoz qisman to'lagan bo'lsa, unga faqat to'lagani qaytariladi.
 *
 * `restock` har satr uchun alohida: bitta ko'zoynak sotuvga yaroqli
 * bo'lib qaytishi, ikkinchisi sinib kelishi mumkin.
 */
class StoreReturnRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', Rule::enum(ReturnReason::class)],
            'refund' => ['nullable', 'numeric', 'min:0', 'max:9999999999999'],
            'refund_method' => ['nullable', Rule::enum(PaymentMethod::class)],

            'items' => ['required', 'array', 'min:1'],
            'items.*.order_item_id' => ['required', 'integer', 'exists:order_items,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'items.*.restock' => ['nullable', 'boolean'],
        ];
    }
}
