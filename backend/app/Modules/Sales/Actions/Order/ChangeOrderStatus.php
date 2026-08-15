<?php

declare(strict_types=1);

namespace App\Modules\Sales\Actions\Order;

use App\Modules\Core\Models\User;
use App\Modules\Sales\Enums\OrderStatus;
use App\Modules\Sales\Events\OrderReady;
use App\Modules\Sales\Models\Order;
use App\Modules\Workshop\Actions\WorkOrder\CreateWorkOrder;
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
    public function __construct(private readonly CreateWorkOrder $createWorkOrder) {}

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

        // Ustaxonaga o'tgan buyurtma uchun ish buyrug'i **tizim
        // tomonidan** ochiladi (§6.6): usta ro'yxatga tushgan ishni
        // ko'radi, uni o'zi yaratmaydi. Shuning uchun PERMISSIONS.md
        // §6 da `work_order.create` ruxsati ham yo'q.
        if ($target === OrderStatus::InWorkshop) {
            $this->createWorkOrder->handle($author, $order);
        }

        // Mijozga "buyurtmangiz tayyor" xabari shu hodisadan keladi
        // (Telegram moduli, BOSQICH-7.md §4.1). `rework` dan qayta
        // `ready` ga qaytish ham qonuniy o'tish (ENUMS.md §4) — har
        // safar chinakam qayta tayyor bo'lganda xabar yana ketishi kerak.
        if ($target === OrderStatus::Ready) {
            event(new OrderReady($order));
        }

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
