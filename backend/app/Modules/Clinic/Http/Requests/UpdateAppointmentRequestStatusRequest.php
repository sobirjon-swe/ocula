<?php

declare(strict_types=1);

namespace App\Modules\Clinic\Http\Requests;

use App\Modules\Clinic\Enums\AppointmentRequestStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAppointmentRequestStatusRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(AppointmentRequestStatus::class)],
        ];
    }
}
