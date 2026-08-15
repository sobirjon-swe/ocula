<?php

declare(strict_types=1);

namespace App\Modules\Telegram\Actions;

use App\Modules\Core\Models\User;
use App\Modules\Sales\Models\Customer;
use App\Modules\Telegram\Models\TelegramLinkCode;

/**
 * Mijozni botga bog'lash kodi — BOSQICH-7.md §3.
 *
 * 6 xonali raqamli kod, `config('optika.telegram.link_code_ttl_minutes')`
 * daqiqa amal qiladi. Faol (ishlatilmagan, muddati o'tmagan) kodlar orasida
 * takrorlanmasligi uchun generatsiya qayta urinadi.
 */
final class GenerateTelegramLinkCode
{
    public function handle(User $author, Customer $customer): TelegramLinkCode
    {
        do {
            $code = (string) random_int(100000, 999999);
        } while (
            TelegramLinkCode::query()
                ->where('code', $code)
                ->where('used_at', null)
                ->where('expires_at', '>', now())
                ->exists()
        );

        return TelegramLinkCode::create([
            'customer_id' => $customer->id,
            'code' => $code,
            'expires_at' => now()->addMinutes((int) config('optika.telegram.link_code_ttl_minutes')),
            'created_by' => $author->id,
        ]);
    }
}
