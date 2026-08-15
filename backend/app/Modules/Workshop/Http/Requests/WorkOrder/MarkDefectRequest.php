<?php

declare(strict_types=1);

namespace App\Modules\Workshop\Http\Requests\WorkOrder;

use App\Modules\Warehouse\Enums\DefectReason;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Brak — PROJECT.md 7.5, §10 ("3 bosishda tugaydi").
 *
 * Usta faqat satrni va sababni tanlaydi. Izoh majburiy: sabab
 * "nima bo'lgani" ni aytmaydi, keyin esa hech kim eslay olmaydi.
 */
class MarkDefectRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'item_id' => ['required', 'integer', 'exists:work_order_items,id'],
            'reason' => ['required', Rule::enum(DefectReason::class)],
            'note' => ['required', 'string', 'min:3', 'max:2000'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'photo_path' => ['nullable', 'string', 'max:255'],
        ];
    }
}
