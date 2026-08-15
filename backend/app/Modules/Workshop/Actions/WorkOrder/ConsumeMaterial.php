<?php

declare(strict_types=1);

namespace App\Modules\Workshop\Actions\WorkOrder;

use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Core\Models\Location;
use App\Modules\Core\Models\User;
use App\Modules\Sales\Models\Order;
use App\Modules\Sales\Models\OrderItem;
use App\Modules\Warehouse\Services\MasterStock;
use App\Modules\Workshop\Models\WorkOrder;
use App\Modules\Workshop\Models\WorkOrderItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Materialni buyurtmaga sarflash — ANALIZ 3.4, PROJECT.md 7.20.
 *
 * Usta linzani o'z zaxirasidan (yoki filial omboridan) oladi va u
 * `consume` harakati bilan chiqadi: bu sotuv emas, lekin tannarxga
 * tushadi.
 *
 * **Ikki marta chiqimning oldini olish.** Buyurtma topshirilganda
 * (`DeliverOrder`) ombordagi satrlar yana chiqarilardi va tovar ikki
 * marta kamayardi. Shuning uchun sarflangan material o'z satrini
 * belgilaydi: harakat `order_items.movement_id` ga, tannarx esa
 * `cost_total` ga yoziladi. `DeliverOrder` belgilangan satrni
 * qaytadan chiqarmaydi.
 */
final class ConsumeMaterial
{
    public function __construct(private readonly MasterStock $masterStock) {}

    /**
     * @param  Location|null  $from  `null` — ustaning o'z zaxirasi
     */
    public function handle(User $master, WorkOrderItem $item, ?Location $from = null): WorkOrderItem
    {
        if ($item->isConsumed()) {
            throw ValidationException::withMessages([
                'item' => __('workshop::work_order.already_consumed'),
            ]);
        }

        $workOrder = $item->workOrder()->withoutGlobalScopes()->firstOrFail();

        return DB::transaction(function () use ($master, $item, $from, $workOrder): WorkOrderItem {
            $source = $from ?? $this->masterStock->locationFor($master, $workOrder->branch_id);

            $movement = $this->masterStock->consume(
                $master,
                $source,
                $item->variant_id,
                $item->quantity,
                $workOrder,
            );

            $item->update(['consumed_movement_id' => $movement->id]);

            $this->markOrderItem($workOrder, $item, $movement->id, $movement->cost_total->toString());

            return $item;
        });
    }

    /**
     * Buyurtmaning mos satriga chiqim harakatini yozadi — topshirishda
     * u ikkinchi marta chiqarilmasin.
     */
    private function markOrderItem(WorkOrder $workOrder, WorkOrderItem $item, int $movementId, string $cost): void
    {
        $order = $workOrder->order()->withoutGlobalScopes()->first();

        if (! $order instanceof Order) {
            return;
        }

        $orderItem = $order->items()
            ->where('itemable_type', ProductVariant::class)
            ->where('itemable_id', $item->variant_id)
            ->whereNull('movement_id')
            ->first();

        if ($orderItem instanceof OrderItem) {
            $orderItem->update([
                'movement_id' => $movementId,
                'cost_total' => $cost,
            ]);
        }
    }
}
