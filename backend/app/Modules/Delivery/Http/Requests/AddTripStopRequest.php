<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Http\Requests;

use App\Modules\Delivery\Enums\TripStopType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Marshrutga to'xtash qo'shish — BOSQICH-8.md §5 #2.
 *
 * `branch` turida `transfer_id`, `customer` turida `order_id` va
 * `address` majburiy — aniq shartlar `AddTripStop` amalida (validatsiya
 * xabari shu yerda, qoidaning o'zi ikkala joyda ham).
 */
class AddTripStopRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(TripStopType::class)],
            'transfer_id' => [
                'required_if:type,branch', 'nullable', 'integer', 'exists:transfers,id',
            ],
            'order_id' => [
                'required_if:type,customer', 'nullable', 'integer', 'exists:orders,id',
            ],
            'address' => ['required_if:type,customer', 'nullable', 'string', 'max:255'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'cash_to_collect' => ['nullable', 'numeric', 'min:0', 'max:9999999999999'],
        ];
    }
}
