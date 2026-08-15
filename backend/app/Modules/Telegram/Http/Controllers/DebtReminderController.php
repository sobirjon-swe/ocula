<?php

declare(strict_types=1);

namespace App\Modules\Telegram\Http\Controllers;

use App\Modules\Sales\Models\Order;
use App\Modules\Telegram\Actions\SendTelegramNotification;
use App\Modules\Telegram\Enums\NotificationType;
use App\Modules\Telegram\Services\CustomerMessage;
use App\Support\Http\ApiController;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

/**
 * Qarz eslatmasini qo'lda yuborish — BOSQICH-7.md §4.2, §5 #3.
 *
 * Avtomatik (`telegram:remind-debts`) eslatmalardan farqli, qo'lda
 * yuborish `dedupe_key` siz — xodim ataylab qayta eslatmoqchi.
 */
final class DebtReminderController extends ApiController
{
    public function store(Order $order, SendTelegramNotification $sender): JsonResponse
    {
        $this->authorize('remindDebt', $order);

        $customer = $order->customer;

        if ($customer === null) {
            throw ValidationException::withMessages([
                'customer_id' => __('telegram::notification.no_customer'),
            ]);
        }

        if ($customer->telegram_id === null) {
            throw ValidationException::withMessages([
                'customer_id' => __('telegram::notification.not_linked'),
            ]);
        }

        $message = CustomerMessage::for($customer, 'telegram::notification.debt_reminder', [
            'number' => $order->number,
            'amount' => $order->debt->toString(),
            'due_date' => $order->due_date?->toDateString() ?? '—',
        ]);

        $notification = $sender->handle($customer, NotificationType::DebtReminder, $message, $order);

        return ApiResponse::created([
            'status' => $notification?->status->value,
        ]);
    }
}
