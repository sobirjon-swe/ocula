<?php

declare(strict_types=1);

namespace App\Modules\Sales\Http\Requests\Order;

use App\Modules\Sales\Enums\OrderStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Bajarilish holatini siljitish — PROJECT.md 7.3.
 *
 * `delivered`, `cancelled` va `returned` bu yerdan qo'yilmaydi: ular
 * ombor va pulga tegadi, shuning uchun o'z endpoint'iga ega. Ruxsat
 * etilgan qiymatlar ro'yxati ham shu sababli qisqartirilgan —
 * validatsiya darajasidayoq to'silsin.
 */
class ChangeOrderStatusRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'status' => [
                'required',
                Rule::enum(OrderStatus::class)->except([
                    OrderStatus::Delivered,
                    OrderStatus::Cancelled,
                    OrderStatus::Returned,
                    OrderStatus::Closed,
                ]),
            ],
        ];
    }
}
