<?php

declare(strict_types=1);

namespace App\Modules\Telegram\Actions;

use App\Modules\Sales\Models\Customer;
use App\Modules\Telegram\Enums\NotificationStatus;
use App\Modules\Telegram\Enums\NotificationType;
use App\Modules\Telegram\Models\SmsNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/** PLAY MOBILE SMS-Broker /send API, the optional Telegram backup channel. */
final class SendSmsFallback
{
    public function handle(Customer $customer, NotificationType $type, string $message, ?string $dedupeKey): ?SmsNotification
    {
        if (! config('services.sms.enabled') || ! config('services.sms.url') || ! config('services.sms.username') || ! config('services.sms.password')) {
            return null;
        }

        return DB::transaction(function () use ($customer, $type, $message, $dedupeKey): ?SmsNotification {
            Customer::query()->whereKey($customer->id)->lockForUpdate()->firstOrFail();
            if ($dedupeKey !== null && SmsNotification::query()->where('dedupe_key', $dedupeKey)->exists()) {
                return null;
            }
            $phone = preg_replace('/\D/', '', $customer->phone);
            $providerId = 'optika-'.Str::uuid();
            try {
                Http::withBasicAuth((string) config('services.sms.username'), (string) config('services.sms.password'))
                    ->timeout(15)->connectTimeout(5)->acceptJson()->post((string) config('services.sms.url'), [
                        'messages' => [[
                            'recipient' => $phone, 'message-id' => $providerId,
                            'sms' => ['originator' => (string) config('services.sms.originator'), 'content' => ['text' => $message]],
                        ]],
                    ])->throw();
                $status = NotificationStatus::Sent;
                $error = null;
            } catch (Throwable $e) {
                $status = NotificationStatus::Failed;
                $error = $e->getMessage();
            }

            return SmsNotification::create([
                'customer_id' => $customer->id, 'phone' => $phone, 'type' => $type->value,
                'dedupe_key' => $dedupeKey, 'provider_message_id' => $providerId,
                'message' => $message, 'status' => $status, 'error' => $error,
            ]);
        });
    }
}
