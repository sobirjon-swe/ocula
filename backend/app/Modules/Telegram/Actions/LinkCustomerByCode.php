<?php

declare(strict_types=1);

namespace App\Modules\Telegram\Actions;

use App\Modules\Sales\Models\Customer;
use App\Modules\Telegram\Models\TelegramLinkCode;

/**
 * `/start <code>` ni qabul qilib mijozni bog'laydi — BOSQICH-7.md §3.
 */
final class LinkCustomerByCode
{
    public function handle(string $code, int $chatId): ?Customer
    {
        // `code` ustuni global unique emas (eskirgan qiymatlar qayta
        // beriladi) — shuning uchun bevosita **faol** yozuvni qidiramiz,
        // aks holda shu qiymatdagi eski (ishlatilgan/eskirgan) qator
        // birinchi topilib, yangi faol kod noto'g'ri rad etilardi.
        /** @var TelegramLinkCode|null $linkCode */
        $linkCode = TelegramLinkCode::query()
            ->where('code', $code)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->with('customer')
            ->first();

        if ($linkCode === null) {
            return null;
        }

        $linkCode->customer->update(['telegram_id' => $chatId]);
        $linkCode->update(['used_at' => now()]);

        return $linkCode->customer;
    }
}
