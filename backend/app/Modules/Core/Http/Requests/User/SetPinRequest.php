<?php

declare(strict_types=1);

namespace App\Modules\Core\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

/**
 * PIN o'rnatish/qayta tiklash — PROJECT.md 7.14.
 *
 * `pin = null` PIN ni olib tashlaydi (xodim planshetdan chiqarilganda).
 */
class SetPinRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $length = (int) config('optika.devices.pin_length', 4);

        return [
            'pin' => ['present', 'nullable', 'string', 'digits:'.$length],
        ];
    }
}
