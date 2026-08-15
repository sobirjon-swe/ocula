<?php

declare(strict_types=1);

namespace App\Modules\Sales\Http\Requests\Order;

use App\Modules\Sales\Enums\OrderDeliveryType;
use App\Modules\Sales\Enums\OrderType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Chek yoki buyurtma yaratish — SCHEMA.md §4, PROJECT.md 7.3.
 *
 * **Narx so'ralmaydi** — u katalogdan olinadi (`CreateOrder`). So'rovda
 * narx bo'lsa, uni qo'lda o'zgartirib istalgan summada sotib yuborish
 * mumkin bo'lardi. Sotuvchining dastagi — `discount`, u limit va
 * ruxsat bilan cheklangan.
 *
 * `items[].kind`:
 * - `variant` — ombordagi tovar, tannarx FIFO dan;
 * - `service` — xizmat; `cost_total` berilsa individual linza sifatida
 *   qaraladi va tannarx qo'lda kiritilgan hisoblanadi (ANALIZ 3.9).
 */
class StoreOrderRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'type' => ['required', Rule::enum(OrderType::class)],
            'delivery_type' => ['nullable', Rule::enum(OrderDeliveryType::class)],
            'due_date' => ['nullable', 'date'],
            'discount' => ['nullable', 'numeric', 'min:0', 'max:9999999999999'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.kind' => ['required', 'string', 'in:variant,service'],
            'items.*.id' => ['required', 'integer', 'min:1'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'items.*.discount' => ['nullable', 'numeric', 'min:0', 'max:9999999999999'],
            'items.*.cost_total' => ['nullable', 'numeric', 'min:0', 'max:9999999999999'],
            'items.*.custom_lens_params' => ['nullable', 'array'],
        ];
    }
}
