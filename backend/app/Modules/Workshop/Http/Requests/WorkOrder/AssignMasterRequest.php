<?php

declare(strict_types=1);

namespace App\Modules\Workshop\Http\Requests\WorkOrder;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Ishni ustaga biriktirish — PERMISSIONS.md §6.
 */
class AssignMasterRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'master_id' => ['required', 'integer', 'exists:users,id'],
        ];
    }
}
