<?php

declare(strict_types=1);

namespace App\Modules\Telegram\Console;

use App\Modules\Telegram\Services\TelegramClient;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * Webhook URL'ni Telegramga ro'yxatdan o'tkazadi — BOSQICH-7.md §7.
 *
 * Deploy vaqtida qo'lda ishga tushiriladi: `php artisan telegram:webhook:set`.
 * URL `APP_URL` dan hosil qilinadi, `TELEGRAM_WEBHOOK_SECRET`
 * `X-Telegram-Bot-Api-Secret-Token` sifatida qaytadi (§5 #5).
 */
final class SetTelegramWebhook extends Command
{
    protected $signature = 'telegram:webhook:set';

    protected $description = "Telegram webhook URL'ini o'rnatadi";

    public function handle(TelegramClient $client): int
    {
        $secret = config('services.telegram.webhook_secret');

        if (! is_string($secret) || $secret === '') {
            $this->error('TELEGRAM_WEBHOOK_SECRET sozlanmagan.');

            return self::FAILURE;
        }

        $url = rtrim((string) config('app.url'), '/').'/api/v1/telegram/webhook';

        try {
            $client->setWebhook($url, $secret);
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Webhook o'rnatildi: {$url}");

        return self::SUCCESS;
    }
}
