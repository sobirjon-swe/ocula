<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Resources;

use App\Modules\Catalog\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Xizmat — narxi to'g'ridan-to'g'ri o'zida (SCHEMA.md §2).
 *
 * @mixin Service
 */
class ServiceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'price' => $this->price->toString(),
            'price_formatted' => $this->price->format(),
            'duration_min' => $this->duration_min,
            'type' => $this->type->value,
            'is_active' => $this->is_active,
        ];
    }
}
