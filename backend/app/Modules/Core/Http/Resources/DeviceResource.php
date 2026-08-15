<?php

declare(strict_types=1);

namespace App\Modules\Core\Http\Resources;

use App\Modules\Core\Models\Device;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Qurilma — SCHEMA.md §1, PROJECT.md 7.14.
 *
 * Token bu yerda **hech qachon** chiqmaydi: bazada u faqat hash, ochiq
 * qiymati esa ro'yxatdan o'tkazish javobida bir marta beriladi
 * (`DeviceController::store`).
 *
 * @mixin Device
 */
class DeviceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'branch_id' => $this->branch_id,
            'name' => $this->name,
            'type' => $this->type->value,
            'allowed_roles' => $this->allowed_roles,
            'is_active' => $this->is_active,
            'last_seen_at' => $this->last_seen_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
