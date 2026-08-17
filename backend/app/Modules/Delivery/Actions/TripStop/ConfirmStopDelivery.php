<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Actions\TripStop;

use App\Modules\Delivery\Enums\ConfirmationStatus;
use App\Modules\Delivery\Models\TripStop;

/**
 * Mijoz "Ha" dedi — PROJECT.md 7.4, BOSQICH-8.md §4.
 *
 * Telegram webhookdan (`callback_query`) chaqiriladi. Faqat `awaiting`
 * holatidan qabul qilinadi — allaqachon javob berilgan yoki muddati
 * o'tgan tugmani qayta bosish e'tiborsiz qoldiriladi.
 */
final class ConfirmStopDelivery
{
    public function handle(TripStop $stop): TripStop
    {
        if ($stop->confirmation_status !== ConfirmationStatus::Awaiting) {
            return $stop;
        }

        $stop->update([
            'confirmation_status' => ConfirmationStatus::Confirmed,
            'customer_confirmed_at' => now(),
        ]);

        return $stop;
    }
}
