<?php

declare(strict_types=1);

namespace App\Modules\Core\Http\Requests\Branch;

use App\Modules\Core\Enums\BranchType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Filialni tahrirlash.
 *
 * `code` **o'zgartirilmaydi** — u allaqachon chiqarilgan hujjat
 * raqamlarida turibdi (ANALIZ 3.12), o'zgartirish tarixni buzadi.
 */
class UpdateBranchRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'type' => ['sometimes', 'required', Rule::enum(BranchType::class)],
            'address' => ['nullable', 'string', 'max:255'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'open_time' => ['nullable', 'date_format:H:i'],
            'close_time' => ['nullable', 'date_format:H:i'],
            'phone' => ['nullable', 'string', 'max:32'],
            'is_active' => ['boolean'],
        ];
    }
}
