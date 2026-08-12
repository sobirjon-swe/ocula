<?php

declare(strict_types=1);

namespace App\Modules\Warehouse\Http\Resources;

use App\Modules\Warehouse\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Yetkazib beruvchi — SCHEMA.md §3.
 *
 * @mixin Supplier
 */
class SupplierResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'payment_terms_days' => $this->payment_terms_days,
            'notes' => $this->notes,
            'is_active' => $this->is_active,
            'purchases_count' => $this->whenCounted('purchases'),
        ];
    }
}
