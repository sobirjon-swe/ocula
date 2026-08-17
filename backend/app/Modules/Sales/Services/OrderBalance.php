<?php

declare(strict_types=1);

namespace App\Modules\Sales\Services;

use App\Modules\Finance\Services\DebtRegistry;
use App\Modules\Sales\Enums\OrderStatus;
use App\Modules\Sales\Enums\PaymentStatus;
use App\Modules\Sales\Models\Order;
use App\Support\Money\Money;

/**
 * Buyurtmaning to'lov holatini `payments` dan qayta hisoblaydi.
 *
 * To'lov, storno va qaytarish — uchalasi ham bir xil savolga javob
 * berishi kerak: "shu buyurtma bo'yicha qancha pul tushdi?". Javob
 * bitta joyda hisoblansin, aks holda uchta amal uchta xil natija
 * berib qo'yishi mumkin.
 *
 * `orders.paid` va `orders.debt` — kesh (`stock_balances` ombor uchun
 * qanday bo'lsa, shunday). Haqiqat manbai — `payments` yig'indisi.
 */
final class OrderBalance
{
    public function __construct(private readonly DebtRegistry $debts) {}

    public function refresh(Order $order): Order
    {
        $paid = $order->paidAmount();
        $debt = $order->total->minus($paid);

        $order->update([
            'paid' => $paid->toString(),
            'debt' => $debt->isNegative() ? Money::zero()->toString() : $debt->toString(),
            'payment_status' => $this->statusFor($order, $paid)->value,
        ]);

        $this->closeIfSettled($order);
        $this->debts->syncForOrder($order);

        return $order;
    }

    private function statusFor(Order $order, Money $paid): PaymentStatus
    {
        // Qaytarilgan buyurtma o'z holatini saqlaydi — `CreateReturn` uni
        // `refunded` qilib qo'ygan, qayta hisob uni buzmasligi kerak.
        if ($order->payment_status === PaymentStatus::Refunded) {
            return PaymentStatus::Refunded;
        }

        if (! $paid->isPositive()) {
            return PaymentStatus::Unpaid;
        }

        return $paid->greaterThanOrEqual($order->total)
            ? PaymentStatus::Paid
            : PaymentStatus::Partial;
    }

    /**
     * Tovar ham berilgan, pul ham to'liq tushgan — buyurtma yopiladi
     * (ENUMS.md §4: `delivered → closed`).
     */
    private function closeIfSettled(Order $order): void
    {
        if ($order->status !== OrderStatus::Delivered) {
            return;
        }

        if ($order->payment_status !== PaymentStatus::Paid) {
            return;
        }

        $order->update([
            'status' => OrderStatus::Closed,
            'status_changed_at' => now(),
        ]);
    }
}
