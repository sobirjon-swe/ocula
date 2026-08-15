<?php

declare(strict_types=1);

namespace App\Modules\Sales\Actions\Order;

use App\Modules\Core\Models\Location;
use App\Modules\Core\Models\User;
use App\Modules\Sales\Enums\OrderStatus;
use App\Modules\Sales\Enums\PaymentStatus;
use App\Modules\Sales\Models\Order;
use App\Modules\Sales\Models\OrderItem;
use App\Modules\Warehouse\Enums\MovementType;
use App\Modules\Warehouse\Services\StockLedger;
use App\Support\Money\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Buyurtmani topshirish — PROJECT.md 7.8, 7.20.
 *
 * **Tovar ombordan aynan shu paytda chiqadi** va daromad shu paytda
 * tan olinadi. Kechagi buyurtma bugun topshirilsa, daromad bugungi
 * kunga tushadi — shuning uchun `revenue_recognized_at` ustuni bor.
 *
 * Tez savdo ham, buyurtma ham shu bitta yo'ldan o'tadi: ikkita alohida
 * chiqim mantig'i bo'lsa, ular vaqt o'tib bir-biridan ajralib ketardi.
 *
 * Tannarx `StockLedger::issue()` qaytargan harakatdan olinadi — FIFO
 * qatlamlari sarflangandan keyingina u ma'lum bo'ladi (7.20).
 */
final class DeliverOrder
{
    public function __construct(private readonly StockLedger $ledger) {}

    public function handle(User $author, Order $order): Order
    {
        if (! $order->status->canBeDelivered()) {
            throw ValidationException::withMessages([
                'status' => __('sales::order.not_deliverable', ['status' => $order->status->value]),
            ]);
        }

        return DB::transaction(function () use ($author, $order): Order {
            $location = $this->warehouseOf($order);

            $cost = Money::zero();

            foreach ($order->items()->get() as $item) {
                $cost = $cost->plus($this->issueItem($author, $order, $item, $location));
            }

            $now = now();

            $order->update([
                'status' => OrderStatus::Delivered,
                'status_changed_at' => $now,
                'delivered_at' => $now,
                'revenue_recognized_at' => $now,
                'cost_total' => $cost->toString(),
            ]);

            // To'lov allaqachon tugagan bo'lsa (avans bilan kelgan
            // buyurtma), topshirish bilan birga yopiladi.
            if ($order->payment_status === PaymentStatus::Paid) {
                $order->update(['status' => OrderStatus::Closed, 'status_changed_at' => $now]);
            }

            return $order;
        });
    }

    /**
     * Satrni ombordan chiqaradi va uning tannarxini qaytaradi.
     *
     * Xizmat va individual linza ombordan o'tmaydi: birinchisining
     * tannarxi umuman yo'q, ikkinchisiniki qo'lda kiritilgan (3.9) —
     * ikkalasi ham allaqachon satrda turibdi.
     */
    private function issueItem(User $author, Order $order, OrderItem $item, Location $location): Money
    {
        if (! $item->cost_source->touchesStock()) {
            return $item->cost_total;
        }

        // Material ustaxonada allaqachon sarflangan bo'lsa
        // (`consume`, ANALIZ 3.4), u ombordan bir marta chiqib
        // bo'lgan. Qaytadan chiqarsak, tovar ikki marta kamayardi.
        if ($item->movement_id !== null) {
            return $item->cost_total;
        }

        $movement = $this->ledger->issue(
            $author,
            $location,
            $item->itemable_id,
            $item->quantity,
            MovementType::Sale,
            $order,
        );

        $item->update([
            'cost_total' => $movement->cost_total->toString(),
            'movement_id' => $movement->id,
        ]);

        return $movement->cost_total;
    }

    /**
     * 1-versiyada har filialda bitta ombor bo'ladi va sotuv shundan
     * chiqadi (§15 #22).
     */
    private function warehouseOf(Order $order): Location
    {
        $location = $order->branch()->firstOrFail()->warehouse();

        if (! $location instanceof Location) {
            throw ValidationException::withMessages([
                'branch_id' => __('sales::order.branch_has_no_warehouse'),
            ]);
        }

        return $location;
    }
}
