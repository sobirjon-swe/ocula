<?php

declare(strict_types=1);

namespace App\Modules\Telegram\Actions;

use App\Modules\Sales\Models\Customer;
use App\Modules\Telegram\Enums\NotificationStatus;
use App\Modules\Telegram\Enums\NotificationType;
use App\Modules\Telegram\Models\TelegramNotification;
use App\Modules\Telegram\Services\TelegramClient;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * Mijozga bitta bildirishnoma yuboradi va jurnalga yozadi — BOSQICH-7.md §4.
 *
 * Mijozda `telegram_id` yo'q bo'lsa — jimgina o'tkazib yuboriladi (sotuvchi
 * qo'lda xabar beradi, bu xato emas). `dedupeKey` berilgan bo'lsa va shu
 * kalit bilan yozuv allaqachon bor bo'lsa — qayta yuborilmaydi (kunlik
 * eslatma buyrug'i ikki marta ishga tushsa ham dublikat bo'lmasin).
 */
final class SendTelegramNotification
{
    public function __construct(private readonly TelegramClient $client) {}

    public function handle(
        Customer $customer,
        NotificationType $type,
        string $message,
        ?Model $source = null,
        ?string $dedupeKey = null,
    ): ?TelegramNotification {
        if ($customer->telegram_id === null) {
            return null;
        }

        if ($dedupeKey !== null && TelegramNotification::query()->where('dedupe_key', $dedupeKey)->exists()) {
            return null;
        }

        try {
            $this->client->sendMessage($customer->telegram_id, $message);
            $status = NotificationStatus::Sent;
            $error = null;
        } catch (Throwable $exception) {
            $status = NotificationStatus::Failed;
            $error = $exception->getMessage();
        }

        return TelegramNotification::create([
            'customer_id' => $customer->id,
            'chat_id' => $customer->telegram_id,
            'type' => $type,
            'source_type' => $source?->getMorphClass(),
            'source_id' => $source?->getKey(),
            'dedupe_key' => $dedupeKey,
            'message' => $message,
            'status' => $status,
            'error' => $error,
        ]);
    }
}
