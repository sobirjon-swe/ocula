<?php

declare(strict_types=1);

namespace App\Modules\Core\Http\Resources;

use App\Modules\Core\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Bitta sozlama yozuvi — SCHEMA.md §1.
 *
 * @mixin Setting
 */
class SettingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'key' => $this->key,
            'value' => $this->value,
            'updated_by' => $this->updated_by,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
