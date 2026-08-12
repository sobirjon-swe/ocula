<?php

declare(strict_types=1);

namespace App\Modules\Core\Http\Requests\User;

use App\Modules\Core\Models\User;
use App\Support\Enums\Locale;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Xodimni tahrirlash.
 *
 * Rol, qarz limiti va PIN bu yerda **o'zgarmaydi** — ularning har biri
 * alohida ruxsat talab qiladi (PERMISSIONS.md §1), shuning uchun
 * alohida endpointda.
 */
class UpdateUserRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $route = $this->route('user');
        $id = $route instanceof User ? $route->getKey() : $route;

        return [
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'phone' => ['sometimes', 'required', 'string', 'max:32', Rule::unique('users', 'phone')->ignore($id)],
            'email' => ['nullable', 'email', 'max:160', Rule::unique('users', 'email')->ignore($id)],
            'password' => ['sometimes', 'string', Password::default()],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'locale' => ['nullable', Rule::enum(Locale::class)],
            'is_active' => ['boolean'],
        ];
    }
}
