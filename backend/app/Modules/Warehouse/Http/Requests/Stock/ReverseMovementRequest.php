<?php

declare(strict_types=1);

namespace App\Modules\Warehouse\Http\Requests\Stock;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Storno — PROJECT.md 7.21.
 *
 * `reason` **majburiy**: storno audit izining bir qismi, sababsiz u
 * hech narsani tushuntirmaydi.
 */
class ReverseMovementRequest extends FormRequest
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
