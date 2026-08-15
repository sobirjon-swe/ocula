<?php

declare(strict_types=1);

namespace App\Modules\Warehouse\Http\Requests\Transfer;

use App\Modules\Warehouse\Enums\DeliveryMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Transferni jo'natish — PROJECT.md 7.10.
 *
 * Taksi tanlanganda xarajat **majburiy** va chek rasmi so'raladi.
 * Qolgan usullarda esa yetkazuvchi xodim ko'rsatilishi shart: "yo'lda"
 * turgan qoldiqning egasi bo'lishi kerak (ANALIZ 3.2).
 *
 * Shartlar `required_if` bilan bu yerda ham, `SendTransfer` da ham
 * tekshiriladi: validatsiya foydalanuvchiga tushunarli xabar beradi,
 * Action esa qoidani API'ning boshqa yo'lidan kelganda ham saqlaydi.
 */
class SendTransferRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'delivery_method' => ['required', Rule::enum(DeliveryMethod::class)],
            'carrier_id' => [
                'required_if:delivery_method,own_driver,by_hand',
                'nullable', 'integer', 'exists:users,id',
            ],
            'taxi_cost' => [
                'required_if:delivery_method,taxi',
                'nullable', 'numeric', 'gt:0', 'max:9999999999999',
            ],
            'taxi_receipt_path' => ['nullable', 'string', 'max:255'],
        ];
    }
}
