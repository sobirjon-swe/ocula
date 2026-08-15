<?php

declare(strict_types=1);

namespace App\Modules\Workshop\Http\Requests\WorkOrder;

use App\Modules\Workshop\Enums\WorkOrderPriority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Muhimlik darajasi — ENUMS.md §6.
 */
class SetPriorityRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'priority' => ['required', Rule::enum(WorkOrderPriority::class)],
        ];
    }
}
