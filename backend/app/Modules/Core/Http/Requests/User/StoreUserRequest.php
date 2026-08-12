<?php

declare(strict_types=1);

namespace App\Modules\Core\Http\Requests\User;

use App\Modules\Core\Enums\Role;
use App\Support\Enums\Locale;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Yangi xodim — SCHEMA.md §1.
 *
 * Login telefon bo'yicha, email ixtiyoriy: sotuvchi va haydovchida
 * email bo'lmasligi mumkin.
 */
class StoreUserRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:32', 'unique:users,phone'],
            'email' => ['nullable', 'email', 'max:160', 'unique:users,email'],
            'password' => ['required', 'string', Password::default()],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'locale' => ['nullable', Rule::enum(Locale::class)],
            'is_active' => ['boolean'],
            'roles' => ['array'],
            'roles.*' => [Rule::in(array_column(Role::staff(), 'value'))],
        ];
    }
}
