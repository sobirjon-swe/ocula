<?php

declare(strict_types=1);

namespace App\Modules\Sales\Http\Resources;

use App\Modules\Sales\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Buyurtma satri — SCHEMA.md §4.
 *
 * Tannarx `catalog.cost.view` ostida: sotuvchida bu ruxsat ataylab
 * yo'q, aks holda chegirma berishda tannarxga qarab savdolashardi
 * (PERMISSIONS.md nozikliklar #1).
 *
 * @mixin OrderItem
 */
class OrderItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $seesCost = $request->user()?->can('catalog.cost.view') ?? false;

        return [
            'id' => $this->id,
            'itemable_type' => class_basename($this->itemable_type),
            'itemable_id' => $this->itemable_id,
            'quantity' => $this->quantity,
            'price' => $this->price->toString(),
            'discount' => $this->discount->toString(),
            'total' => $this->total->toString(),
            'total_formatted' => $this->total->format(),
            'cost_total' => $this->when($seesCost, fn (): string => $this->cost_total->toString()),
            'cost_source' => $this->when($seesCost, fn (): string => $this->cost_source->value),
            'custom_lens_params' => $this->custom_lens_params,
            'movement_id' => $this->movement_id,
            'returned_quantity' => $this->returnedQuantity(),
        ];
    }
}
