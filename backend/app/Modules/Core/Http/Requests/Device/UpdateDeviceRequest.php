<?php

declare(strict_types=1);

namespace App\Modules\Core\Http\Requests\Device;

use App\Modules\Core\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Qurilma sozlamalarini tahrirlash — PROJECT.md 7.14.
 *
 * `branch_id`, `type` va `token` o'zgarmaydi: qurilma boshqa filialga
 * ko'chsa, u yerda qaytadan ro'yxatdan o'tishi kerak — aks holda eski
 * filialning PIN'lari yangi joyda ishlab ketardi.
 */
class UpdateDeviceRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80'],
            'allowed_roles' => ['nullable', 'array'],
            'allowed_roles.*' => [Rule::enum(Role::class)->except([Role::Customer])],
        ];
    }
}
