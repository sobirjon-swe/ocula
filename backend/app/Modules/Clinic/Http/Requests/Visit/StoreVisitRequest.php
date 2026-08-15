<?php

declare(strict_types=1);

namespace App\Modules\Clinic\Http\Requests\Visit;

use App\Modules\Clinic\Enums\VisitSource;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Navbatga qo'shish — SCHEMA.md §5.
 *
 * `queue_number` so'ralmaydi — uni tizim beradi (filial va kun
 * kesimida), aks holda ikki sotuvchi bir xil raqam yozib yuborardi.
 */
class StoreVisitRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'doctor_id' => ['nullable', 'integer', 'exists:users,id'],
            'source' => ['nullable', Rule::enum(VisitSource::class)],
        ];
    }
}
