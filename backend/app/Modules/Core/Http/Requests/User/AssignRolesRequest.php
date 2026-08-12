<?php

declare(strict_types=1);

namespace App\Modules\Core\Http\Requests\User;

use App\Modules\Core\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Rol biriktirish — PERMISSIONS.md §1 (`core.user.assign_role`).
 *
 * `customer` bu yerda yo'q: u alohida guard, `users` jadvalida emas (§4).
 */
class AssignRolesRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'roles' => ['present', 'array'],
            'roles.*' => [Rule::in(array_column(Role::staff(), 'value'))],
        ];
    }
}
