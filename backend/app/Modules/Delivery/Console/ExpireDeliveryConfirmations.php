<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Console;

use App\Modules\Core\Enums\SettingKey;
use App\Modules\Core\Models\Setting;
use App\Modules\Delivery\Enums\ConfirmationStatus;
use App\Modules\Delivery\Models\TripStop;
use Illuminate\Console\Command;

/**
 * Javobsiz qolgan yetkazishlarni yopadi — PROJECT.md 7.4, BOSQICH-8.md §4.
 *
 * "Javob yo'q 24 soat → avto-yopiladi, lekin `unconfirmed` belgisi
 * bilan" — `confirmed` emas, chunki mijoz haqiqatan ham javob
 * bermagan, buni "roziligi bor" deb bo'lmaydi.
 */
final class ExpireDeliveryConfirmations extends Command
{
    protected $signature = 'delivery:expire-confirmations';

    protected $description = "Javobsiz qolgan yetkazish tasdiqlarini 'unconfirmed' bilan yopadi";

    public function handle(): int
    {
        $hours = (int) Setting::valueFor(SettingKey::DeliveryAutoConfirmAfterHours);
        $threshold = now()->subHours($hours);

        $count = TripStop::query()
            ->where('confirmation_status', ConfirmationStatus::Awaiting)
            ->where('delivered_at', '<=', $threshold)
            ->update(['confirmation_status' => ConfirmationStatus::Unconfirmed]);

        $this->info("Yopildi: {$count}");

        return self::SUCCESS;
    }
}
