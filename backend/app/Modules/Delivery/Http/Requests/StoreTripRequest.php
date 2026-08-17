<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Yo'l varaqasi yaratish — BOSQICH-8.md §5 #1.
 */
class StoreTripRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'driver_id' => ['required', 'integer', 'exists:users,id'],
            'date' => ['required', 'date'],
        ];
    }
}
