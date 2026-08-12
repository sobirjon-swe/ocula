<?php

declare(strict_types=1);

namespace App\Modules\Core\Http\Requests\Branch;

use App\Modules\Core\Enums\BranchType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Yangi filial — SCHEMA.md §1.
 *
 * `code` hujjat raqamiga kiradi (`A-2608-00147`, ANALIZ 3.12), shuning
 * uchun qisqa, katta harfli va noyob bo'lishi shart.
 */
class StoreBranchRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'code' => ['required', 'string', 'max:8', 'regex:/^[A-Z0-9]+$/', 'unique:branches,code'],
            'type' => ['required', Rule::enum(BranchType::class)],
            'address' => ['nullable', 'string', 'max:255'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'open_time' => ['nullable', 'date_format:H:i'],
            'close_time' => ['nullable', 'date_format:H:i'],
            'phone' => ['nullable', 'string', 'max:32'],
            'is_active' => ['boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('code')) {
            $this->merge(['code' => mb_strtoupper($this->string('code')->trim()->toString())]);
        }
    }
}
