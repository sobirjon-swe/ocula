<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Requests\Cash;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Kassa yozuvining stornosi — PROJECT.md 7.21.
 *
 * Izoh majburiy: storno yozuvi hisobotda "nega" degan savolsiz qolsa,
 * keyin uni hech kim tushuntira olmaydi.
 */
class ReverseCashMovementRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:3', 'max:255'],
        ];
    }
}
