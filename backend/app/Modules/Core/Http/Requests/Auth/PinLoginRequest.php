<?php

declare(strict_types=1);

namespace App\Modules\Core\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Umumiy planshetda PIN bilan almashish — PROJECT.md 7.14.
 *
 * Planshet bir marta qurilma sifatida ro'yxatdan o'tadi (`device_id` +
 * `device_token`), keyin xodimlar 4 xonali PIN bilan 2 soniyada
 * almashadi — har amal aniq `user_id` ga yoziladi.
 */
class PinLoginRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        $length = (int) config('optika.devices.pin_length', 4);

        return [
            'device_id' => ['required', 'integer'],
            'device_token' => ['required', 'string'],
            'pin' => ['required', 'string', 'digits:'.$length],
        ];
    }
}
