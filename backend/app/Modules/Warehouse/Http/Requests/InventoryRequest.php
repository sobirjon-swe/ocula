<?php

declare(strict_types=1);

namespace App\Modules\Warehouse\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InventoryRequest extends FormRequest
{
    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        if ($this->isMethod('put')) {
            return [
                'items' => ['required', 'array', 'min:1'],
                'items.*.variant_id' => ['required', 'integer', 'distinct', 'exists:product_variants,id'],
                'items.*.actual_qty' => ['required', 'integer', 'min:0', 'max:1000000'],
                'items.*.unit_cost' => ['nullable', 'decimal:0,2', 'min:0', 'max:9999999999999'],
            ];
        }

        return ['location_id' => ['required', 'integer', 'exists:locations,id']];
    }
}
