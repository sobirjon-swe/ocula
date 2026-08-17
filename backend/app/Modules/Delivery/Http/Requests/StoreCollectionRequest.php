<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Inkassatsiya — haydovchidan kassaga pul topshirish (BOSQICH-8.md §3).
 */
class StoreCollectionRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'driver_id' => ['required', 'integer', 'exists:users,id'],
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'shift_id' => ['nullable', 'integer', 'exists:shifts,id'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999999'],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
