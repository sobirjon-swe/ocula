<?php

declare(strict_types=1);

namespace App\Modules\Telegram\Listeners;

use App\Modules\Delivery\Events\StopDelivered;
use App\Modules\Telegram\Actions\SendTelegramNotification;
use App\Modules\Telegram\Enums\NotificationType;
use App\Modules\Telegram\Services\CustomerMessage;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Haydovchi yetkazgach mijozga "Yetkazildimi?" savoli — PROJECT.md 7.4,
 * BOSQICH-8.md §4.
 *
 * Javob Telegram inline tugmasi orqali keladi (`callback_data`:
 * `delivery:confirm:{stopId}` / `delivery:dispute:{stopId}`) —
 * `TelegramWebhookController` ushlaydi.
 */
final class AskDeliveryConfirmation implements ShouldQueue
{
    public function __construct(private readonly SendTelegramNotification $sender) {}

    public function handle(StopDelivered $event): void
    {
        $stop = $event->stop->loadMissing('customer');
        $customer = $stop->customer;

        if ($customer === null) {
            return;
        }

        $message = CustomerMessage::for($customer, 'telegram::notification.delivery_confirmation');

        $this->sender->handle(
            $customer,
            NotificationType::DeliveryConfirmation,
            $message,
            $stop,
            replyMarkup: [
                'inline_keyboard' => [[
                    ['text' => __('telegram::notification.confirmation_yes'), 'callback_data' => "delivery:confirm:{$stop->id}"],
                    ['text' => __('telegram::notification.confirmation_no'), 'callback_data' => "delivery:dispute:{$stop->id}"],
                ]],
            ],
        );
    }
}
