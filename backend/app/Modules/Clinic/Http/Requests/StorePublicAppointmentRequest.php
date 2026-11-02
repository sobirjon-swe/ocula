<?php

declare(strict_types=1);

namespace App\Modules\Clinic\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Landing saytdan onlayn navbat so'rovi — BOSQICH-11.md.
 *
 * Hisobsiz, `auth:sanctum` dan tashqarida — shuning uchun `authorize()`
 * har doim `true` (himoya `throttle` middleware'da, spam'dan).
 */
class StorePublicAppointmentRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $phone = $this->input('phone');
        if (is_string($phone) && preg_match('/^[+0-9 ()-]+$/', $phone)) {
            $this->merge(['phone' => '+'.preg_replace('/\D/', '', $phone)]);
        }
    }

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
            'branch_id' => ['required', 'integer', Rule::exists('branches', 'id')->where('is_active', true)],
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'regex:/^\+[0-9]{7,15}$/'],
            'preferred_date' => ['nullable', 'date', 'after_or_equal:'.now('Asia/Tashkent')->toDateString()],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
