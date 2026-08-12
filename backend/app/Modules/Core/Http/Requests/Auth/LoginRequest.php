<?php

declare(strict_types=1);

namespace App\Modules\Core\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Kompyuterdan kirish — telefon + parol (SCHEMA.md §1).
 *
 * `device_name` token nomiga yoziladi: xodim "qaysi qurilmalarda
 * kirganman" ro'yxatini ko'radi va keraksizini bekor qila oladi.
 */
class LoginRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', 'max:32'],
            'password' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:120'],
        ];
    }
}
