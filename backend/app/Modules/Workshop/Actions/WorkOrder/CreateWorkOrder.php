<?php

declare(strict_types=1);

namespace App\Modules\Workshop\Actions\WorkOrder;

use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Core\Models\User;
use App\Modules\Sales\Models\Order;
use App\Modules\Sales\Models\OrderItem;
use App\Modules\Workshop\Enums\WorkOrderStatus;
use App\Modules\Workshop\Models\WorkOrder;
use Illuminate\Support\Facades\DB;

/**
 * Ish buyrug'ini tug'dirish — PROJECT.md §6.6, 7.3.
 *
 * Buyruq **qo'lda yaratilmaydi**: buyurtma ustaxonaga o'tganda
 * (`in_workshop`) tizim uni o'zi ochadi. Shuning uchun PERMISSIONS.md
 * §6 da `work_order.create` ruxsati yo'q — usta ro'yxatga tushgan
 * ishni ko'radi, uni o'zi yaratmaydi.
 *
 * Materiallar buyurtmaning **ombordagi** satrlaridan olinadi: xizmat
 * satrini ustaxonaga berib bo'lmaydi, unda material yo'q.
 *
 * Bitta buyurtmaga bitta ochiq buyruq: qayta ishlash yangi hujjat
 * emas, o'sha buyruqning `rework_count` i (SCHEMA.md §6).
 */
final class CreateWorkOrder
{
    public function handle(User $author, Order $order, ?string $dueAt = null): ?WorkOrder
    {
        $existing = WorkOrder::query()
            ->withoutGlobalScopes()
            ->where('order_id', $order->id)
            ->first();

        if ($existing instanceof WorkOrder) {
            return $existing;
        }

        $materials = $this->materialsOf($order);

        if ($materials === []) {
            return null;
        }

        return DB::transaction(function () use ($author, $order, $materials, $dueAt): WorkOrder {
            $workOrder = WorkOrder::create([
                'order_id' => $order->id,
                'branch_id' => $order->branch_id,
                'status' => WorkOrderStatus::Queued,
                'due_at' => $dueAt,
                'created_by' => $author->id,
            ]);

            foreach ($materials as $variantId => $quantity) {
                $workOrder->items()->create([
                    'variant_id' => $variantId,
                    'quantity' => $quantity,
                ]);
            }

            return $workOrder;
        });
    }

    /**
     * Buyurtmaning ombordan chiqadigan satrlari, variant bo'yicha
     * yig'ilgan holda.
     *
     * @return array<int, int> variant_id => miqdor
     */
    private function materialsOf(Order $order): array
    {
        $materials = [];

        foreach ($order->items()->get() as $item) {
            if (! $this->isStockItem($item)) {
                continue;
            }

            $materials[$item->itemable_id] = ($materials[$item->itemable_id] ?? 0) + $item->quantity;
        }

        return $materials;
    }

    private function isStockItem(OrderItem $item): bool
    {
        return $item->itemable_type === ProductVariant::class
            && $item->cost_source->touchesStock();
    }
}
