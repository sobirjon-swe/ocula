<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Http\Requests;

use App\Modules\Payroll\Enums\PlanType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBranchPlanRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'period' => ['required', 'date_format:Y-m'],
            'type' => ['required', Rule::enum(PlanType::class)],
            'target_amount' => ['required', 'numeric', 'gt:0', 'max:9999999999999'],
        ];
    }
}
