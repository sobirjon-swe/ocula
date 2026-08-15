<?php

declare(strict_types=1);

namespace App\Modules\Workshop\Actions\WorkOrder;

use App\Modules\Core\Models\Location;
use App\Modules\Core\Models\User;
use App\Modules\Sales\Enums\OrderStatus;
use App\Modules\Sales\Models\Order;
use App\Modules\Warehouse\Actions\Defect\ReportDefect;
use App\Modules\Warehouse\Enums\DefectReason;
use App\Modules\Warehouse\Models\Defect;
use App\Modules\Warehouse\Models\StockMovement;
use App\Modules\Warehouse\Services\MasterStock;
use App\Modules\Workshop\Enums\WorkOrderStatus;
use App\Modules\Workshop\Models\WorkOrder;
use App\Modules\Workshop\Models\WorkOrderItem;
use App\Support\Money\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Ustaxonada brak — PROJECT.md 7.5, §10 ("Brak → sabab tanlash,
 * 3 bosishda tugaydi").
 *
 * Usta **faqat sababni tanlaydi**, qolganini tizim qiladi:
 *
 * - buzilgan material hisobdan chiqariladi va zarar qiymati yoziladi;
 * - ish buyrug'i `defect` holatiga o'tadi;
 * - sabab `customer_request` bo'lsa, buyurtma **qayta ishlashga
 *   qaytadi** (7.5): buyruq yana navbatga tushadi va `rework_count`
 *   oshadi, buyurtma esa `rework` bo'ladi.
 *
 * **Material allaqachon sarflangan bo'lsa, ombor ikkinchi marta
 * tegilmaydi.** `consume` harakati uni daftardan chiqarib bo'lgan;
 * yana `defect` yozsak, mavjud bo'lmagan tovar hisobdan chiqarilardi.
 * O'shanda faqat zarar qiymati yoziladi — u sarflash paytidagi
 * tannarxdan olinadi.
 */
final class MarkDefect
{
    public function __construct(
        private readonly ReportDefect $reportDefect,
        private readonly MasterStock $masterStock,
    ) {}

    public function handle(
        User $master,
        WorkOrder $workOrder,
        WorkOrderItem $item,
        DefectReason $reason,
        string $note,
        int $quantity = 1,
        ?string $photoPath = null,
    ): Defect {
        if (! $workOrder->status->canBeDefective()) {
            throw ValidationException::withMessages([
                'status' => __('workshop::work_order.not_defective', ['status' => $workOrder->status->value]),
            ]);
        }

        if ($quantity < 1 || $quantity > $item->quantity) {
            throw ValidationException::withMessages([
                'quantity' => __('workshop::work_order.defect_quantity', ['max' => $item->quantity]),
            ]);
        }

        return DB::transaction(function () use (
            $master, $workOrder, $item, $reason, $note, $quantity, $photoPath
        ): Defect {
            $consumed = $item->isConsumed();

            $defect = $this->reportDefect->handle(
                $master,
                $this->sourceOf($master, $workOrder, $item),
                $item->variant_id,
                $quantity,
                $reason,
                $note,
                [
                    'order_id' => $workOrder->order_id,
                    'work_order_id' => $workOrder->id,
                    'photo_path' => $photoPath,
                ],
                // Sarflangan material daftardan allaqachon chiqqan.
                fromStock: ! $consumed,
                costImpact: $consumed ? $this->consumedCost($item, $quantity) : null,
                document: $workOrder,
            );

            if ($reason->reopensOrder()) {
                $this->reopen($workOrder);
            } else {
                $workOrder->update(['status' => WorkOrderStatus::Defect]);
            }

            return $defect;
        });
    }

    /**
     * Sarflangan materialning zarar qiymati — `consume` harakatining
     * tannarxidan, buzilgan miqdorga nisbatan.
     */
    private function consumedCost(WorkOrderItem $item, int $quantity): Money
    {
        $movement = $item->consumedMovement()->first();

        if (! $movement instanceof StockMovement || $item->quantity < 1) {
            return Money::zero();
        }

        return $movement->cost_total->absolute()
            ->dividedBy($item->quantity)
            ->multipliedBy($quantity);
    }

    /**
     * Qayta ishlash — buyruq yana navbatga tushadi, buyurtma `rework`
     * bo'ladi (7.3, 7.5). Yangi hujjat ochilmaydi: qayta ishlash
     * o'sha buyruqning hisoblagichi (SCHEMA.md §6).
     */
    private function reopen(WorkOrder $workOrder): void
    {
        $workOrder->update([
            'status' => WorkOrderStatus::Queued,
            'rework_count' => $workOrder->rework_count + 1,
            'started_at' => null,
        ]);

        $order = $workOrder->order()->withoutGlobalScopes()->first();

        if ($order instanceof Order) {
            $order->update([
                'status' => OrderStatus::Rework,
                'status_changed_at' => now(),
            ]);
        }
    }

    /**
     * Brak qaysi joyga yoziladi.
     *
     * Sarflangan material ustaning zaxirasidan chiqib bo'lgan —
     * hujjatda o'sha joy ko'rsatiladi, lekin yangi ombor harakati
     * yozilmaydi. Sarflanmagani esa filial omboridan chiqariladi.
     */
    private function sourceOf(User $master, WorkOrder $workOrder, WorkOrderItem $item): Location
    {
        if ($item->isConsumed()) {
            return $this->masterStock->locationFor($master, $workOrder->branch_id);
        }

        $location = $workOrder->branch()->firstOrFail()->warehouse();

        if (! $location instanceof Location) {
            throw ValidationException::withMessages([
                'branch_id' => __('workshop::work_order.branch_has_no_warehouse'),
            ]);
        }

        return $location;
    }
}
