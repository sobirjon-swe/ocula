<?php

declare(strict_types=1);

namespace App\Modules\Warehouse\Http\Requests\Transfer;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Miqdor farqini hal qilish — PROJECT.md 7.4.
 *
 * Xulosa majburiy: farq sababsiz yopilsa, kamomad statistikasi
 * "shunchaki bo'ldi" degan qatorlardan iborat bo'lib qolardi.
 */
class ResolveDiscrepancyRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'resolution' => ['required', 'string', 'min:3', 'max:500'],
        ];
    }
}
