<?php

declare(strict_types=1);

namespace App\Modules\Sales\Actions\Order;

use App\Modules\Finance\Services\DebtRegistry;
use App\Modules\Sales\Enums\OrderStatus;
use App\Modules\Sales\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Buyurtmani bekor qilish — ENUMS.md §4.
 *
 * Bekor qilish **topshirilmagan** buyurtma uchun: ombor hali tegmagan,
 * shuning uchun teskari harakat ham kerak emas. Topshirilgandan keyin
 * bekor qilish yo'q — u **qaytarish** bo'ladi (tovar va pul allaqachon
 * harakatlangan).
 *
 * Avans olingan buyurtma ham to'g'ridan-to'g'ri bekor qilinmaydi: avval
 * pul qaytariladi (to'lov stornosi), keyin hujjat yopiladi. Aks holda
 * kassada egasi yo'q pul qolib ketardi.
 *
 * Bekor qilish sababi uchun sxemada ustun yo'q — kim va qachon bekor
 * qilgani `activity_log` da qoladi.
 */
final class CancelOrder
{
    public function handle(Order $order): Order
    {
        return DB::transaction(function () use ($order): Order {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);
            if (! $order->status->canBeCancelled()) {
                throw ValidationException::withMessages([
                    'status' => __('sales::order.not_cancellable', ['status' => $order->status->value]),
                ]);
            }

            if ($order->paidAmount()->isPositive()) {
                throw ValidationException::withMessages([
                    'status' => __('sales::order.cancel_with_payment'),
                ]);
            }

            $now = now();

            $order->update([
                'status' => OrderStatus::Cancelled,
                'status_changed_at' => $now,
                'cancelled_at' => $now,
                'debt' => '0.00',
            ]);
            app(DebtRegistry::class)->syncForOrder($order);

            return $order;
        });
    }
}
