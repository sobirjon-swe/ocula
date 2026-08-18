<?php

declare(strict_types=1);

namespace App\Modules\Clinic\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Landing saytdan onlayn navbat so'rovi — BOSQICH-11.md.
 *
 * Hisobsiz, `auth:sanctum` dan tashqarida — shuning uchun `authorize()`
 * har doim `true` (himoya `throttle` middleware'da, spam'dan).
 */
class StorePublicAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:32'],
            'preferred_date' => ['nullable', 'date', 'after_or_equal:today'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
