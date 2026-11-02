<?php

declare(strict_types=1);

namespace App\Modules\Warehouse\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PostSupplierReturnRequest extends FormRequest
{
    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return ['purchase_id' => ['required', 'integer', 'exists:purchases,id']];
    }
}
