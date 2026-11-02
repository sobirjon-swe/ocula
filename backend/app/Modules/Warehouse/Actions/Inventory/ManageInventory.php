<?php

declare(strict_types=1);

namespace App\Modules\Warehouse\Actions\Inventory;

use App\Modules\Core\Models\Branch;
use App\Modules\Core\Models\Location;
use App\Modules\Core\Models\User;
use App\Modules\Warehouse\Enums\MovementType;
use App\Modules\Warehouse\Models\Inventory;
use App\Modules\Warehouse\Models\StockBalance;
use App\Modules\Warehouse\Models\StockMovement;
use App\Modules\Warehouse\Services\StockLedger;
use App\Modules\Warehouse\Services\StockMutationLock;
use App\Support\Documents\DocumentNumber;
use App\Support\Money\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ManageInventory
{
    public function __construct(private readonly StockLedger $ledger) {}

    public function start(User $actor, Location $location): Inventory
    {
        return DB::transaction(function () use ($actor, $location): Inventory {
            StockMutationLock::acquire();
            Location::query()->whereKey($location->id)->lockForUpdate()->firstOrFail();
            if (Inventory::query()->where('location_id', $location->id)->whereIn('status', ['counting', 'review'])->exists()) {
                $this->invalid('already_open');
            }
            $branch = Branch::findOrFail($location->branch_id);
            $inventory = Inventory::create([
                'number' => DocumentNumber::next('inventory', $branch->id, $branch->code),
                'branch_id' => $branch->id, 'location_id' => $location->id,
                'started_by' => $actor->id, 'started_at' => now(), 'status' => 'counting',
                'snapshot_movement_id' => StockMovement::query()->where('location_id', $location->id)->max('id') ?? 0,
            ]);
            foreach (StockBalance::query()->where('location_id', $location->id)->get() as $balance) {
                $inventory->items()->create(['variant_id' => $balance->variant_id, 'expected_qty' => $balance->quantity]);
            }

            return $inventory;
        });
    }

    /** @param array<int,array{variant_id:int,actual_qty:int,unit_cost?:string|null}> $items */
    public function count(User $actor, Inventory $inventory, array $items): Inventory
    {
        return DB::transaction(function () use ($actor, $inventory, $items): Inventory {
            $inventory = Inventory::query()->lockForUpdate()->findOrFail($inventory->id);
            if ($inventory->status !== 'counting') {
                $this->invalid('not_counting');
            }
            foreach ($items as $row) {
                $item = $inventory->items()->firstOrCreate(['variant_id' => $row['variant_id']], ['expected_qty' => 0]);
                $item->update([
                    'actual_qty' => $row['actual_qty'], 'unit_cost' => $row['unit_cost'] ?? null,
                    'counted_by' => $actor->id, 'counted_at' => now(),
                ]);
            }

            return $inventory;
        });
    }

    public function transition(User $actor, Inventory $inventory, string $next): Inventory
    {
        return DB::transaction(function () use ($actor, $inventory, $next): Inventory {
            StockMutationLock::acquire();
            $inventory = Inventory::query()->lockForUpdate()->findOrFail($inventory->id);
            $allowed = ['review' => ['counting'], 'completed' => ['review'], 'cancelled' => ['counting', 'review']];
            if (! in_array($inventory->status, $allowed[$next] ?? [], true)) {
                $this->invalid('wrong_status');
            }
            if ($next !== 'cancelled' && $inventory->items()->whereNull('actual_qty')->exists()) {
                $this->invalid('incomplete');
            }
            if ($next === 'completed') {
                // The shared location lock serializes stock movement and finalization.
                $location = Location::query()->lockForUpdate()->findOrFail($inventory->location_id);
                $last = StockMovement::query()->where('location_id', $location->id)->max('id') ?? 0;
                if ((int) $last !== (int) $inventory->snapshot_movement_id) {
                    $this->invalid('stock_changed');
                }
                foreach ($inventory->items()->get() as $item) {
                    $delta = $item->actual_qty - $item->expected_qty;
                    $movement = null;
                    $reason = $inventory->getAttribute('number');
                    if ($delta > 0) {
                        if ($item->unit_cost === null) {
                            $this->invalid('cost_required');
                        }
                        $movement = $this->ledger->receive($actor, $location, $item->variant_id, $delta, Money::of($item->unit_cost), MovementType::Adjustment, $inventory, $reason);
                    } elseif ($delta < 0) {
                        $movement = $this->ledger->issue($actor, $location, $item->variant_id, -$delta, MovementType::WriteOff, $inventory, $reason);
                    }
                    if ($movement !== null) {
                        $item->update(['movement_id' => $movement->id]);
                    }
                }
            }
            $inventory->update(['status' => $next, ...($next === 'review' ? [] : ['completed_by' => $actor->id, 'finished_at' => now()])]);

            return $inventory;
        });
    }

    private function invalid(string $key): never
    {
        throw ValidationException::withMessages(['inventory' => __('warehouse::inventory.'.$key)]);
    }
}
