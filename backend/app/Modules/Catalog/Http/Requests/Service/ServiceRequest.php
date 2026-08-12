<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Requests\Service;

use App\Modules\Catalog\Enums\ServiceType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Xizmat — SCHEMA.md §2.
 */
class ServiceRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'name' => [$required, 'string', 'max:120'],
            'price' => [$required, 'numeric', 'min:0', 'max:9999999999999'],
            'type' => [$required, Rule::enum(ServiceType::class)],
            'duration_min' => ['nullable', 'integer', 'min:1', 'max:1440'],
            'is_active' => ['boolean'],
        ];
    }
}
