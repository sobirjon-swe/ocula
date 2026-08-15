<?php

declare(strict_types=1);

namespace App\Modules\Core\Http\Requests\Setting;

use App\Modules\Core\Enums\SettingKey;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Sozlamalarni yangilash — SCHEMA.md §1.
 *
 * Ekran bir nechta qiymatni birga saqlaydi, shuning uchun so'rov ham
 * bir nechtasini oladi. Har kalit **o'z qoidalari** bilan tekshiriladi
 * (`SettingKey::rules()`), noma'lum kalit esa umuman o'tmaydi: ochiq
 * ro'yxat bo'lganda xato yozilgan kalit jimgina yangi qator yasab,
 * eski qiymat ishlab yuraverardi.
 *
 * Kalitlar ixtiyoriy: faqat yuborilgani yangilanadi.
 */
class UpdateSettingsRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $rules = [];

        foreach (SettingKey::cases() as $key) {
            $rules[$key->value] = ['sometimes', ...$key->rules()];

            if ($key->itemRules() !== []) {
                $rules[$key->value.'.*'] = $key->itemRules();
            }
        }

        return $rules;
    }
}
