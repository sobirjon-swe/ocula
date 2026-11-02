<?php

declare(strict_types=1);

namespace App\Modules\Core\Http\Requests\Auth;

use App\Support\Enums\Locale;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLocaleRequest extends FormRequest
{
    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return ['locale' => ['required', Rule::enum(Locale::class)]];
    }
}
