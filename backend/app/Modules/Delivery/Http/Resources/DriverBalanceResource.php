<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Http\Resources;

use App\Modules\Delivery\Models\DriverBalance;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Haydovchi qo'lidagi pul — SCHEMA.md, PROJECT.md §5.2.
 *
 * @mixin DriverBalance
 */
class DriverBalanceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'driver_id' => $this->driver_id,
            'cash_amount' => $this->cash_amount->toString(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
