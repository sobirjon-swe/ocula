<?php

declare(strict_types=1);

namespace App\Modules\Telegram\Console;

use App\Modules\Clinic\Models\Prescription;
use App\Modules\Core\Enums\SettingKey;
use App\Modules\Core\Models\Setting;
use App\Modules\Telegram\Actions\SendTelegramNotification;
use App\Modules\Telegram\Enums\NotificationStatus;
use App\Modules\Telegram\Enums\NotificationType;
use App\Modules\Telegram\Services\CustomerMessage;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Ko'rik/retsept eslatmasi — PROJECT.md 7.11, BOSQICH-7.md §4.3.
 *
 * Retsept muddati (`valid_until`) tugashidan
 * `SettingKey::CheckupReminderDaysBefore` (standart 14) kun oldin bitta
 * xabar. Retsept tarixi hammaga ko'rinsa ham (7.11), eslatma faqat
 * mijozning o'ziga — filial bilan bog'liq emas.
 */
final class SendCheckupReminders extends Command
{
    protected $signature = 'telegram:remind-checkups';

    protected $description = "Retsept muddati yaqinlashgan mijozlarga ko'rik eslatmasi yuboradi";

    public function handle(SendTelegramNotification $sender): int
    {
        $daysBefore = (int) Setting::valueFor(SettingKey::CheckupReminderDaysBefore);
        $targetDate = CarbonImmutable::today()->addDays($daysBefore);
        $sent = 0;

        $prescriptions = Prescription::query()
            ->whereDate('valid_until', $targetDate)
            ->with('customer')
            ->get();

        foreach ($prescriptions as $prescription) {
            if ($prescription->customer === null) {
                continue;
            }

            $message = CustomerMessage::for($prescription->customer, 'telegram::notification.checkup_reminder', [
                'valid_until' => $prescription->valid_until->toDateString(),
            ]);

            $dedupeKey = sprintf('checkup_reminder:prescription:%d', $prescription->id);

            $result = $sender->handle(
                $prescription->customer,
                NotificationType::CheckupReminder,
                $message,
                $prescription,
                $dedupeKey,
            );

            if ($result?->status === NotificationStatus::Sent) {
                $sent++;
            }
        }

        $this->info("Ko'rik eslatmalari yuborildi: {$sent}");

        return self::SUCCESS;
    }
}
