<?php

declare(strict_types=1);

namespace App\Modules\Core\Http\Requests\Shift;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Smena ochish — PROJECT.md 5.2.
 *
 * `opening_cash` — smena boshidagi seyfdagi naqd.
 */
class OpenShiftRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'opening_cash' => ['required', 'numeric', 'min:0', 'max:9999999999999'],
        ];
    }
}
