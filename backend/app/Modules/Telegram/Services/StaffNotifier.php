<?php

declare(strict_types=1);

namespace App\Modules\Telegram\Services;

use App\Modules\Core\Models\User;
use App\Modules\Telegram\Models\StaffNotification;
use App\Support\Enums\Locale;
use App\Support\Text\Transliterator;
use Illuminate\Support\Facades\DB;
use Throwable;

final class StaffNotifier
{
    public function __construct(private readonly TelegramClient $telegram) {}

    /** @param array<string, string|int> $replacements */
    public function director(string $dedupeKey, string $translation, array $replacements = []): bool
    {
        $chatId = filter_var(config('services.telegram.director_chat_id'), FILTER_VALIDATE_INT);
        if ($chatId === false || $chatId === 0) {
            return false;
        }
        $director = User::role('director', 'web')->where('is_active', true)->orderBy('id')->first();
        $locale = $director === null ? Locale::default() : $director->locale;
        $message = __($translation, $replacements, $locale->sourceLocale()->value);
        if ($locale->isDerived()) {
            $message = Transliterator::toCyrillic($message);
        }
        $locale = $locale->value;

        return DB::transaction(function () use ($dedupeKey, $chatId, $locale, $message): bool {
            $notification = StaffNotification::firstOrCreate(['dedupe_key' => $dedupeKey], [
                'chat_id' => $chatId, 'locale' => $locale, 'message' => $message,
                'status' => 'pending', 'attempts' => 0,
            ]);
            $notification = StaffNotification::query()->lockForUpdate()->findOrFail($notification->id);
            if ($notification->status === 'sent') {
                return true;
            }
            try {
                $this->telegram->sendMessage($chatId, $message);
                $notification->update(['status' => 'sent', 'sent_at' => now(), 'error_code' => null, 'attempts' => $notification->attempts + 1]);

                return true;
            } catch (Throwable) {
                // Provider exceptions can contain bot-token URLs. Persist only
                // a stable code; queue retries receive a sanitized exception.
                $notification->update(['status' => 'failed', 'error_code' => 'delivery_failed', 'attempts' => $notification->attempts + 1]);

                return false;
            }
        });
    }
}
