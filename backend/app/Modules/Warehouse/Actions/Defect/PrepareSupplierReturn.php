<?php

declare(strict_types=1);

namespace App\Modules\Warehouse\Actions\Defect;

use App\Modules\Warehouse\Enums\DefectReason;
use App\Modules\Warehouse\Models\Defect;
use App\Modules\Warehouse\Models\Purchase;
use App\Modules\Warehouse\Models\StockMovement;
use App\Modules\Warehouse\Models\SupplierReturn;
use App\Support\Money\Money;

final class PrepareSupplierReturn
{
    public function handle(Defect $defect): void
    {
        if ($defect->reason !== DefectReason::SupplierDefect) {
            return;
        }
        $movement = $defect->movement;
        $assigned = 0;
        if ($movement instanceof StockMovement) {
            $parts = [];
            foreach ($movement->consumptions()->with('layer.movement')->get() as $consumption) {
                $origin = $consumption->layer->movement;
                $purchaseId = $consumption->layer->getAttribute('origin_purchase_id') ?? ($origin?->source_type === Purchase::class ? $origin->source_id : null);
                if ($purchaseId === null) {
                    continue;
                }
                $purchase = Purchase::withoutGlobalScopes()->find($purchaseId);
                if (! $purchase instanceof Purchase) {
                    continue;
                }
                $key = $purchase->id;
                $parts[$key] ??= ['purchase' => $purchase, 'quantity' => 0, 'amount' => Money::zero()];
                $parts[$key]['quantity'] += $consumption->quantity;
                $parts[$key]['amount'] = $parts[$key]['amount']->plus($consumption->total_cost);
            }
            foreach ($parts as $part) {
                SupplierReturn::create([
                    'branch_id' => $defect->branch_id, 'defect_id' => $defect->id,
                    'purchase_id' => $part['purchase']->id, 'supplier_id' => $part['purchase']->supplier_id,
                    'quantity' => $part['quantity'], 'amount' => $part['amount']->toString(),
                ]);
                $assigned += $part['quantity'];
            }
        }
        if ($assigned < $defect->quantity) {
            // Unknown opening stock or inter-branch provenance needs explicit purchase selection.
            SupplierReturn::create([
                'branch_id' => $defect->branch_id, 'defect_id' => $defect->id,
                'quantity' => $defect->quantity - $assigned,
                'amount' => $defect->cost_impact->dividedBy($defect->quantity)->multipliedBy($defect->quantity - $assigned)->toString(),
            ]);
        }
    }
}
