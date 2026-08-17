<?php

declare(strict_types=1);

namespace App\Modules\Customer\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Telegram `initData` orqali kirish — BOSQICH-9.md §3.
 */
class TelegramAuthRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'init_data' => ['required', 'string'],
        ];
    }
}
