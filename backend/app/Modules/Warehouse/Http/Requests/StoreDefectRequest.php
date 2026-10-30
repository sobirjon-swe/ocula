<?php

declare(strict_types=1);

namespace App\Modules\Warehouse\Http\Requests;

use App\Modules\Warehouse\Enums\DefectReason;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDefectRequest extends FormRequest
{
    /** @return array<string,array<int,mixed>> */
    public function rules(): array
    {
        return [
            'location_id' => ['required', 'integer', 'exists:locations,id'],
            'variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'quantity' => ['required', 'integer', 'between:1,100000'],
            'reason' => ['required', Rule::enum(DefectReason::class)],
            'note' => ['nullable', 'string', 'max:2000'],
            'transfer_id' => ['nullable', 'integer', 'exists:transfers,id'],
        ];
    }
}
