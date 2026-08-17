<?php

declare(strict_types=1);

namespace App\Modules\Telegram\Console;

use App\Modules\Core\Enums\SettingKey;
use App\Modules\Core\Models\Setting;
use App\Modules\Finance\Actions\Debt\RecordDebtReminder;
use App\Modules\Finance\Enums\ReminderChannel;
use App\Modules\Finance\Models\Debt;
use App\Modules\Telegram\Actions\SendTelegramNotification;
use App\Modules\Telegram\Enums\NotificationStatus;
use App\Modules\Telegram\Enums\NotificationType;
use App\Modules\Telegram\Services\CustomerMessage;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Qarz eslatmasi — PROJECT.md 7.6, BOSQICH-7.md §4.2, BOSQICH-10.md §10a.
 *
 * Endi `orders.debt` o'rniga **`debts`** jadvalidan o'qiydi — dedupe
 * mantig'i o'zgarmadi (`telegram_notifications.dedupe_key`), lekin
 * yuborilgach endi **`debt_reminders`** ga ham yozadi
 * (`channel=telegram`) — CRM jurnali, xodim keyin qo'lda ham eslatma
 * qo'shishi mumkin.
 *
 * Eslatma kunlari sozlanadigan: `SettingKey::DebtReminderDays`, standart
 * `[-1, 0, 3, 7]` — muddatdan 1 kun oldin, muddat kuni, +3, +7.
 */
final class SendDebtReminders extends Command
{
    protected $signature = 'telegram:remind-debts';

    protected $description = "Muddati yaqinlashgan/o'tgan qarzlar bo'yicha mijozlarga Telegram eslatma yuboradi";

    public function handle(SendTelegramNotification $sender, RecordDebtReminder $reminder): int
    {
        /** @var array<int, int> $offsets */
        $offsets = (array) Setting::valueFor(SettingKey::DebtReminderDays);
        $today = CarbonImmutable::today();
        $sent = 0;

        foreach ($offsets as $offset) {
            $dueDate = $today->subDays((int) $offset);

            $debts = Debt::query()
                ->withoutGlobalScopes()
                ->whereDate('due_date', $dueDate)
                ->with(['customer', 'order'])
                ->get();

            foreach ($debts as $debt) {
                if ($debt->customer === null || $debt->order === null || ! $debt->remaining()->isPositive()) {
                    continue;
                }

                $message = CustomerMessage::for($debt->customer, 'telegram::notification.debt_reminder', [
                    'number' => $debt->order->number,
                    'amount' => $debt->remaining()->toString(),
                    'due_date' => $debt->due_date->toDateString(),
                ]);

                $dedupeKey = sprintf('debt_reminder:order:%d:offset:%d', $debt->order_id, $offset);

                $result = $sender->handle(
                    $debt->customer,
                    NotificationType::DebtReminder,
                    $message,
                    $debt->order,
                    $dedupeKey,
                );

                if ($result?->status === NotificationStatus::Sent) {
                    $sent++;
                    $reminder->handle(null, $debt, ReminderChannel::Telegram);
                }
            }
        }

        $this->info("Qarz eslatmalari yuborildi: {$sent}");

        return self::SUCCESS;
    }
}
