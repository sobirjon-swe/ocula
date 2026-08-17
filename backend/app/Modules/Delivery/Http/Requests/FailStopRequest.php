<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Topshira olmadi — sababi majburiy (ENUMS.md §7).
 */
class FailStopRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:3', 'max:255'],
        ];
    }
}
