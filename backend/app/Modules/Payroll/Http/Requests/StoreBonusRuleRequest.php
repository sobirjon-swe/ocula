<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Http\Requests;

use App\Modules\Core\Enums\Role;
use App\Modules\Payroll\Enums\BonusBase;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Mukofot qoidasi — SCHEMA.md `bonus_rules`, PROJECT.md 7.19.
 *
 * `user_id` yoki `role` dan kamida bittasi bo'lishi shart (baza
 * `CHECK` cheklovi bilan bir xil qoida — xato bazadan emas, shu yerdan
 * qaytsin).
 */
class StoreBonusRuleRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'role' => ['nullable', 'string', Rule::in(array_map(
                static fn (Role $role): string => $role->value,
                Role::staff(),
            ))],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'base' => ['required', Rule::enum(BonusBase::class)],
            'percent' => ['required', 'numeric', 'min:0', 'max:999.99'],
            'valid_from' => ['required', 'date'],
            'valid_to' => ['nullable', 'date', 'after_or_equal:valid_from'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->filled('user_id') || $this->filled('role')) {
                return;
            }

            $validator->errors()->add('user_id', __('payroll::bonus_rule.user_or_role_required'));
        });
    }
}
