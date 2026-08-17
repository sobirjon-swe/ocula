<?php

declare(strict_types=1);

namespace App\Modules\Telegram\Services;

/**
 * Telegram Mini App `initData` imzosini tekshiradi — BOSQICH-9.md §3.
 *
 * Telegramning rasmiy algoritmi: `secret_key = HMAC_SHA256(bot_token,
 * "WebAppData")`, so'ng `hash = HMAC_SHA256(data_check_string, secret_key)`.
 * `data_check_string` — `hash` dan boshqa maydonlar, kalit bo'yicha
 * saralangan, `key=value` qatorlari `\n` bilan qo'shilgan.
 *
 * Bu klass faqat imzoni tekshiradi — mijozni topish/yaratish
 * `Customer\Actions\AuthenticateViaTelegram` ishi.
 */
final class InitDataValidator
{
    public function __construct(private readonly ?string $botToken) {}

    /**
     * @return array<string, string>|null To'g'ri bo'lsa xom maydonlar
     *                                    (`user` — hali JSON qator), aks holda `null`.
     */
    public function validate(string $initData, int $maxAgeSeconds = 86_400): ?array
    {
        if ($this->botToken === null || $this->botToken === '') {
            return null;
        }

        parse_str($initData, $fields);

        if (! is_array($fields) || ! isset($fields['hash']) || ! is_string($fields['hash'])) {
            return null;
        }

        /** @var array<string, string> $fields */
        $hash = $fields['hash'];
        unset($fields['hash']);

        ksort($fields);

        $dataCheckString = collect($fields)
            ->map(static fn (string $value, string $key): string => "{$key}={$value}")
            ->implode("\n");

        $secretKey = hash_hmac('sha256', $this->botToken, 'WebAppData', true);
        $computedHash = hash_hmac('sha256', $dataCheckString, $secretKey);

        if (! hash_equals($computedHash, $hash)) {
            return null;
        }

        $authDate = isset($fields['auth_date']) ? (int) $fields['auth_date'] : 0;

        if ($authDate <= 0 || (time() - $authDate) > $maxAgeSeconds) {
            return null;
        }

        return $fields;
    }
}
