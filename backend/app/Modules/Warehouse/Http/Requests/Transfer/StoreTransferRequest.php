<?php

declare(strict_types=1);

namespace App\Modules\Warehouse\Http\Requests\Transfer;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Transfer qoralamasi — SCHEMA.md §3.
 *
 * Yetkazish usuli bu yerda so'ralmaydi: hujjat tayyorlanayotganda
 * kim olib borishi hali ma'lum bo'lmaydi, u jo'natish paytida
 * tanlanadi (`SendTransferRequest`).
 */
class StoreTransferRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'from_location_id' => ['required', 'integer', 'exists:locations,id'],
            'to_location_id' => ['required', 'integer', 'exists:locations,id', 'different:from_location_id'],
            'note' => ['nullable', 'string', 'max:2000'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
        ];
    }
}
