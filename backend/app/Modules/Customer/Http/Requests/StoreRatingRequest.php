<?php

declare(strict_types=1);

namespace App\Modules\Customer\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRatingRequest extends FormRequest
{
    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return ['stars' => ['required', 'integer', 'between:1,5'], 'comment' => ['nullable', 'string', 'max:1000']];
    }
}
