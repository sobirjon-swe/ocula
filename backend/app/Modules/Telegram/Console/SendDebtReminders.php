<?php

declare(strict_types=1);

namespace App\Modules\Telegram\Console;

use App\Modules\Core\Enums\SettingKey;
use App\Modules\Core\Models\Setting;
use App\Modules\Sales\Models\Order;
use App\Modules\Telegram\Actions\SendTelegramNotification;
use App\Modules\Telegram\Enums\NotificationStatus;
use App\Modules\Telegram\Enums\NotificationType;
use App\Modules\Telegram\Services\CustomerMessage;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Qarz eslatmasi — PROJECT.md 7.6, BOSQICH-7.md §4.2.
 *
 * `debts` alohida jadvali hali yo'q (Finance/Bosqich 10) — to'g'ridan-to'g'ri
 * `orders.debt` / `orders.due_date` dan o'qiydi (BOSQICH-7.md §5 #2).
 *
 * Eslatma kunlari sozlanadigan: `SettingKey::DebtReminderDays`, standart
 * `[-1, 0, 3, 7]` — muddatdan 1 kun oldin, muddat kuni, +3, +7.
 */
final class SendDebtReminders extends Command
{
    protected $signature = 'telegram:remind-debts';

    protected $description = "Muddati yaqinlashgan/o'tgan qarzlar bo'yicha mijozlarga Telegram eslatma yuboradi";

    public function handle(SendTelegramNotification $sender): int
    {
        /** @var array<int, int> $offsets */
        $offsets = (array) Setting::valueFor(SettingKey::DebtReminderDays);
        $today = CarbonImmutable::today();
        $sent = 0;

        foreach ($offsets as $offset) {
            $dueDate = $today->subDays((int) $offset);

            $orders = Order::query()
                ->whereNotNull('customer_id')
                ->whereNotNull('due_date')
                ->whereDate('due_date', $dueDate)
                ->where('debt', '>', 0)
                ->with('customer')
                ->get();

            foreach ($orders as $order) {
                if ($order->customer === null) {
                    continue;
                }

                $message = CustomerMessage::for($order->customer, 'telegram::notification.debt_reminder', [
                    'number' => $order->number,
                    'amount' => $order->debt->toString(),
                    'due_date' => $order->due_date->toDateString(),
                ]);

                $dedupeKey = sprintf('debt_reminder:order:%d:offset:%d', $order->id, $offset);

                $result = $sender->handle(
                    $order->customer,
                    NotificationType::DebtReminder,
                    $message,
                    $order,
                    $dedupeKey,
                );

                if ($result?->status === NotificationStatus::Sent) {
                    $sent++;
                }
            }
        }

        $this->info("Qarz eslatmalari yuborildi: {$sent}");

        return self::SUCCESS;
    }
}
