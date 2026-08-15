<?php

declare(strict_types=1);

namespace App\Modules\Workshop\Http\Requests\WorkOrder;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Materialni sarflash — ANALIZ 3.4.
 *
 * `location_id` ixtiyoriy: ko'rsatilmasa material ustaning **o'z
 * zaxirasidan** olinadi. Filial omboridan olish ham mumkin —
 * o'shanda joy aniq ko'rsatiladi.
 */
class ConsumeMaterialRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'item_id' => ['required', 'integer', 'exists:work_order_items,id'],
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
        ];
    }
}
