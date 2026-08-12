<?php

declare(strict_types=1);

namespace App\Modules\Core\Http\Requests\Shift;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Smena yopish — PROJECT.md 5.2.
 *
 * `actual_cash` — seyfdan **sanab olingan** naqd. Kamomad chiqsa
 * `note` da izoh talab qilinadi.
 */
class CloseShiftRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'actual_cash' => ['required', 'numeric', 'min:0', 'max:9999999999999'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
