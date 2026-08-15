<?php

declare(strict_types=1);

namespace App\Modules\Telegram\Listeners;

use App\Modules\Sales\Events\OrderReady;
use App\Modules\Telegram\Actions\SendTelegramNotification;
use App\Modules\Telegram\Enums\NotificationType;
use App\Modules\Telegram\Services\CustomerMessage;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Buyurtma tayyor bo'lganda mijozga Telegram xabari — BOSQICH-7.md §4.1.
 *
 * Navbatga tushadi (`ShouldQueue`): HTTP so'rov mijozning javobini
 * kutib turmaydi, sotuvchi ekrani darrov ochiladi.
 */
final class NotifyCustomerOrderReady implements ShouldQueue
{
    public function __construct(private readonly SendTelegramNotification $sender) {}

    public function handle(OrderReady $event): void
    {
        $order = $event->order->loadMissing('customer');
        $customer = $order->customer;

        if ($customer === null) {
            return;
        }

        $message = CustomerMessage::for($customer, 'telegram::notification.order_ready', [
            'number' => $order->number,
        ]);

        // Dedupe kaliti kerak emas: `ChangeOrderStatus` bir xil holatga
        // qaytadan o'tishni allaqachon jimgina rad etadi (`$order->status
        // === $target` bo'lsa hodisa otilmaydi), shuning uchun hodisa
        // faqat **haqiqiy** o'tishda keladi. `rework`dan qayta `ready`ga
        // qaytish esa qonuniy, yangi o'tish (ENUMS.md §4) — xabar yana
        // ketishi kerak, shuning uchun bu yerda umuman dedupe ishlatilmaydi.
        $this->sender->handle($customer, NotificationType::OrderReady, $message, $order);
    }
}
