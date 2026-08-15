<?php

declare(strict_types=1);

namespace App\Modules\Core\Http\Requests\Device;

use App\Modules\Core\Enums\DeviceType;
use App\Modules\Core\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Qurilmani ro'yxatdan o'tkazish — PROJECT.md 7.14.
 *
 * `token` so'ralmaydi — uni tizim o'zi yasaydi va javobda bir marta
 * qaytaradi. Aks holda kimdir "1234" ni token qilib qo'yardi.
 *
 * `allowed_roles` ro'yxati `customer` dan tashqari xodim rollari bilan
 * cheklangan: mijoz alohida guard'da, planshetga PIN bilan kirmaydi.
 */
class RegisterDeviceRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'name' => ['required', 'string', 'max:80'],
            'type' => ['required', Rule::enum(DeviceType::class)],
            'allowed_roles' => ['nullable', 'array'],
            'allowed_roles.*' => [Rule::enum(Role::class)->except([Role::Customer])],
        ];
    }
}
