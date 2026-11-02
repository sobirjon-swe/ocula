<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Haydovchining "Yetkazdim" tasdig'i — PROJECT.md 7.4.
 *
 * GPS ham, rasm ham ixtiyoriy: internet yo'qligida GPS olinmasligi
 * mumkin, bu yetkazishni to'xtatmasligi kerak.
 */
class DeliverStopRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'photo' => ['nullable', 'string', 'max:2800000', 'regex:/^data:image\/(jpeg|png|webp);base64,[A-Za-z0-9+\/=]+$/'],
        ];
    }
}
