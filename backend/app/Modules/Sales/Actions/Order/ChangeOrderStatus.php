<?php

declare(strict_types=1);

namespace App\Modules\Sales\Actions\Order;

use App\Modules\Core\Models\User;
use App\Modules\Sales\Enums\OrderStatus;
use App\Modules\Sales\Models\Order;
use Illuminate\Validation\ValidationException;

/**
 * Bajarilish holatini bir qadam siljitish — PROJECT.md 7.3.
 *
 * Bu amal **faqat holat o'qiga** tegadi: ombor ham, pul ham
 * o'zgarmaydi. Shuning uchun `delivered`, `cancelled` va `returned`
 * bu yerdan qo'yilmaydi — ular o'z amaliga ega (`deliver`, `cancel`,
 * `returns`), aks holda tovar bermasdan turib daromad tan olinardi.
 *
 * To'lov holati (`payment_status`) bunga umuman bog'liq emas:
 * `ready` + `partial` normal juftlik.
 */
final class ChangeOrderStatus
{
    public function handle(User $author, Order $order, OrderStatus $target): Order
    {
        if ($order->status === $target) {
            return $order;
        }

        if (! $order->status->canMoveTo($target)) {
            throw ValidationException::withMessages([
                'status' => __('sales::order.transition_not_allowed', [
                    'from' => $order->status->value,
                    'to' => $target->value,
                    'allowed' => $this->allowedList($order->status),
                ]),
            ]);
        }

        $order->update([
            'status' => $target,
            'status_changed_at' => now(),
        ]);

        return $order;
    }

    /**
     * Xato xabari nima qilish mumkinligini aytsin (§10), "mumkin emas"
     * degan quruq javob bermasin.
     */
    private function allowedList(OrderStatus $status): string
    {
        $allowed = array_map(
            static fn (OrderStatus $next): string => $next->value,
            $status->allowedNext(),
        );

        return $allowed === [] ? '—' : implode(', ', $allowed);
    }
}
