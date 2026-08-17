<?php

declare(strict_types=1);

namespace App\Modules\Customer\Http\Requests;

use App\Support\Enums\Locale;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Mijoz o'z profilini yangilaydi — PERMISSIONS.md §12.
 *
 * `uz-cyrl` bu yerda ham tanlash mumkin: u hosila (§10), lekin
 * foydalanuvchi profilida saqlanadigan **tanlangan** til bo'lishi
 * mumkin — matnlar `Transliterator` orqali chiqadi.
 */
class UpdateProfileRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:160'],
            'phone' => ['sometimes', 'string', 'max:32'],
            'locale' => ['sometimes', Rule::enum(Locale::class)],
        ];
    }
}
