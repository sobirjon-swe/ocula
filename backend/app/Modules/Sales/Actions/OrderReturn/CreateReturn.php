<?php

declare(strict_types=1);

namespace App\Modules\Sales\Actions\OrderReturn;

use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Core\Models\Location;
use App\Modules\Core\Models\User;
use App\Modules\Finance\Enums\CashCategory;
use App\Modules\Finance\Services\CashRegister;
use App\Modules\Sales\Enums\OrderStatus;
use App\Modules\Sales\Enums\PaymentMethod;
use App\Modules\Sales\Enums\PaymentStatus;
use App\Modules\Sales\Enums\PaymentTxStatus;
use App\Modules\Sales\Enums\ReturnReason;
use App\Modules\Sales\Models\Order;
use App\Modules\Sales\Models\OrderItem;
use App\Modules\Sales\Models\OrderReturn;
use App\Modules\Sales\Models\Payment;
use App\Modules\Sales\Services\OrderBalance;
use App\Modules\Warehouse\Actions\Defect\ReportDefect;
use App\Modules\Warehouse\Enums\DefectReason;
use App\Modules\Warehouse\Enums\MovementType;
use App\Modules\Warehouse\Services\StockLedger;
use App\Support\Documents\DocumentNumber;
use App\Support\Money\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Tovarni qaytarish — SCHEMA.md §4 (3.6), PROJECT.md 7.20, 7.21.
 *
 * Uchta narsa bir vaqtda qaytadi va ular bir tranzaksiyada bo'lishi
 * shart, aks holda tovar omborda, pul esa mijozda qolib ketardi:
 *
 * 1. **Tovar** — `restock = true` bo'lsa omborga, **sotilgandagi**
 *    tannarx bilan (yangi qatlam ochiladi). Bugungi narxda qaytarsak,
 *    qaytarish foyda ko'rsatib qo'yardi.
 * 2. **Pul** — manfiy `payment` va (naqd bo'lsa) `refund` kassa yozuvi.
 * 3. **Tannarx** — `returns.cost_total` da. Buyurtmaning `cost_total` i
 *    tegilmaydi: foyda hisoboti sotuvdan qaytarishni ayirib hisoblaydi,
 *    shunda "qancha sotildi" va "qancha qaytdi" ikkalasi ham ko'rinadi.
 *
 * `restock = false` — brak: tovar qoldiqqa qaytmaydi va `defects` ga
 * yoziladi (7.5). Ombor harakati yozilmaydi — tovar sotilganda
 * allaqachon chiqib bo'lgan, uni yana chiqarish qoldiqni ikki marta
 * kamaytirardi. Yozib qo'yiladigani — **zarar qiymati**.
 */
final class CreateReturn
{
    public function __construct(
        private readonly StockLedger $ledger,
        private readonly CashRegister $cash,
        private readonly OrderBalance $balance,
        private readonly ReportDefect $defects,
    ) {}

    /**
     * @param  array<int, array{order_item_id: int, quantity: int, restock?: bool}>  $items
     */
    public function handle(
        User $author,
        Order $order,
        ReturnReason $reason,
        array $items,
        ?Money $refund = null,
        PaymentMethod $refundMethod = PaymentMethod::Cash,
    ): OrderReturn {
        if ($items === []) {
            throw ValidationException::withMessages([
                'items' => __('sales::return.no_items'),
            ]);
        }

        // Qaytarish — topshirilgan tovar uchun. Topshirilmagan buyurtma
        // bekor qilinadi (`CancelOrder`), ombor hali tegmagan.
        if (! in_array($order->status, [OrderStatus::Delivered, OrderStatus::Closed], true)) {
            throw ValidationException::withMessages([
                'order' => __('sales::return.order_not_delivered'),
            ]);
        }

        $this->assertRefundFits($order, $refund);

        return DB::transaction(function () use ($author, $order, $reason, $items, $refund, $refundMethod): OrderReturn {
            $branch = $order->branch()->firstOrFail();

            $return = OrderReturn::create([
                'number' => DocumentNumber::next('return', $branch->id, $branch->code),
                'order_id' => $order->id,
                'branch_id' => $order->branch_id,
                'shift_id' => $order->shift_id,
                'reason' => $reason,
                'created_by' => $author->id,
            ]);

            $amount = Money::zero();
            $cost = Money::zero();

            foreach ($items as $row) {
                [$lineAmount, $lineCost] = $this->addItem($author, $order, $return, $row);

                $amount = $amount->plus($lineAmount);
                $cost = $cost->plus($lineCost);
            }

            $return->update([
                'amount' => $amount->toString(),
                'cost_total' => $cost->toString(),
            ]);

            $this->refund($author, $order, $return, $refund, $refundMethod);
            $this->settleOrder($order);

            return $return;
        });
    }

    /**
     * Bitta satrni qaytaradi va (pul, tannarx) juftligini beradi.
     *
     * @param  array{order_item_id: int, quantity: int, restock?: bool}  $row
     * @return array{0: Money, 1: Money}
     */
    private function addItem(User $author, Order $order, OrderReturn $return, array $row): array
    {
        /** @var OrderItem $item */
        $item = $order->items()->findOrFail($row['order_item_id']);

        $quantity = $row['quantity'];
        $available = $item->quantity - $item->returnedQuantity();

        if ($quantity < 1 || $quantity > $available) {
            throw ValidationException::withMessages([
                'items' => __('sales::return.quantity_exceeds_sold', ['available' => $available]),
            ]);
        }

        $unitCost = $item->unitCost();
        $lineAmount = $item->unitNetPrice()->multipliedBy($quantity);
        $lineCost = $unitCost->multipliedBy($quantity);

        $restock = ($row['restock'] ?? true) && $item->cost_source->touchesStock();
        $movementId = null;

        if ($restock) {
            $movementId = $this->ledger->receive(
                $author,
                $this->warehouseOf($order),
                $item->itemable_id,
                $quantity,
                $unitCost,
                MovementType::Return,
                $return,
            )->id;
        }

        $return->items()->create([
            'order_item_id' => $item->id,
            'variant_id' => $item->itemable_type === ProductVariant::class ? $item->itemable_id : null,
            'quantity' => $quantity,
            'amount' => $lineAmount->toString(),
            'cost_total' => $lineCost->toString(),
            'restock' => $restock,
            'movement_id' => $movementId,
        ]);

        if (! $restock && $item->itemable_type === ProductVariant::class) {
            $this->recordDefect($author, $order, $return, $item, $quantity, $lineCost);
        }

        return [$lineAmount, $lineCost];
    }

    /**
     * Qaytgan brakni `defects` ga yozadi (7.5).
     *
     * Sabab qaytarish sababidan kelib chiqadi: nuqsonli tovar
     * yetkazib beruvchining javobgarligi, qolganlari esa mijoz
     * tomonidan. Aniqrog'ini keyin direktor tuzatishi mumkin — muhimi
     * zarar hisobga tushishi.
     */
    private function recordDefect(
        User $author,
        Order $order,
        OrderReturn $return,
        OrderItem $item,
        int $quantity,
        Money $cost,
    ): void {
        $location = $order->branch()->firstOrFail()->warehouse();

        if (! $location instanceof Location) {
            return;
        }

        $this->defects->handle(
            $author,
            $location,
            $item->itemable_id,
            $quantity,
            $this->reasonFor($return),
            __('sales::return.defect_note', ['number' => $return->number]),
            ['order_id' => $order->id],
            fromStock: false,
            costImpact: $cost,
            document: $return,
        );
    }

    private function reasonFor(OrderReturn $return): DefectReason
    {
        return $return->reason === ReturnReason::ProductDefect
            ? DefectReason::SupplierDefect
            : DefectReason::CustomerRequest;
    }

    /**
     * Pulni qaytarish — manfiy `payment` va naqd bo'lsa kassa chiqimi.
     *
     * Summa alohida beriladi (tovar summasiga avtomatik teng emas):
     * mijoz qisman to'lagan bo'lsa, unga faqat to'lagani qaytariladi.
     */
    private function refund(
        User $author,
        Order $order,
        OrderReturn $return,
        ?Money $refund,
        PaymentMethod $method,
    ): void {
        if ($refund === null || ! $refund->isPositive()) {
            return;
        }

        Payment::create([
            'order_id' => $order->id,
            'customer_id' => $order->customer_id,
            'branch_id' => $order->branch_id,
            'shift_id' => $order->shift_id,
            'amount' => $refund->negated()->toString(),
            'method' => $method,
            'status' => PaymentTxStatus::Completed,
            'reason' => __('sales::return.payment_reason', ['number' => $return->number]),
            'received_by' => $author->id,
            'paid_at' => now(),
        ]);

        if ($method->entersCashRegister()) {
            $this->cash->record(
                $author,
                $order->branch_id,
                CashCategory::Refund,
                $refund,
                source: $return,
            );
        }
    }

    /**
     * Buyurtma to'liq qaytarilgan bo'lsa, ikkala o'q ham yopiladi
     * (`returned` + `refunded`). Qisman qaytarishda buyurtma o'z
     * holatida qoladi — mijozda hali tovar bor.
     */
    private function settleOrder(Order $order): void
    {
        $order->refresh();

        $fullyReturned = $order->items()->get()
            ->every(static fn (OrderItem $item): bool => $item->returnedQuantity() >= $item->quantity);

        if ($fullyReturned) {
            $order->update([
                'status' => OrderStatus::Returned,
                'status_changed_at' => now(),
                'payment_status' => PaymentStatus::Refunded,
            ]);
        }

        $this->balance->refresh($order);
    }

    private function assertRefundFits(Order $order, ?Money $refund): void
    {
        if ($refund === null || ! $refund->isPositive()) {
            return;
        }

        $paid = $order->paidAmount();

        if ($refund->greaterThan($paid)) {
            throw ValidationException::withMessages([
                'refund' => __('sales::return.refund_exceeds_paid', ['paid' => $paid->format()]),
            ]);
        }
    }

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
