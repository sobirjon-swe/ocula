<?php

declare(strict_types=1);

namespace App\Modules\Warehouse\Http\Requests\Transfer;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Transferni qabul qilish — PROJECT.md 7.4.
 *
 * Xodim **haqiqiy miqdorni** kiritadi, jo'natilganini ko'r-ko'rona
 * tasdiqlamaydi. Har satr uchun miqdor majburiy: tushirib qoldirilgan
 * satr "nol keldi" degan ma'noni bermasligi kerak.
 */
class ReceiveTransferRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', 'integer', 'exists:transfer_items,id'],
            'items.*.quantity' => ['required', 'integer', 'min:0', 'max:1000000'],
        ];
    }
}
