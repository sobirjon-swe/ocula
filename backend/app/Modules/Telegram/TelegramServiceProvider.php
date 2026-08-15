<?php

declare(strict_types=1);

namespace App\Modules\Telegram;

use App\Modules\Sales\Events\OrderReady;
use App\Modules\Telegram\Console\SendCheckupReminders;
use App\Modules\Telegram\Console\SendDebtReminders;
use App\Modules\Telegram\Console\SetTelegramWebhook;
use App\Modules\Telegram\Listeners\NotifyCustomerOrderReady;
use App\Modules\Telegram\Services\TelegramClient;
use App\Support\Providers\ModuleServiceProvider;
use Illuminate\Support\Facades\Event;

/**
 * Telegram — bir tomonlama bildirishnoma bot, BOSQICH-7.md.
 *
 * PROJECT.md §11 (Bosqich 7), §6.7, 7.3 (buyurtma tayyor), 7.6 (qarz
 * eslatmasi), 7.11 (ko'rik eslatmasi).
 */
final class TelegramServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TelegramClient::class, static fn (): TelegramClient => new TelegramClient(
            config('services.telegram.bot_token'),
            (string) config('services.telegram.api_base_url'),
        ));
    }

    protected function moduleName(): string
    {
        return 'Telegram';
    }

    public function boot(): void
    {
        parent::boot();

        // Loyihadagi birinchi domen hodisasi (PROJECT.md §9) — Sales
        // moduli mijozga xabar yuborilishini bilmaydi, faqat hodisani otadi.
        Event::listen(OrderReady::class, NotifyCustomerOrderReady::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                SendDebtReminders::class,
                SendCheckupReminders::class,
                SetTelegramWebhook::class,
            ]);
        }
    }
}
