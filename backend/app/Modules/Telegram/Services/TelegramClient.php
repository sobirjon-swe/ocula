<?php

declare(strict_types=1);

namespace App\Modules\Telegram\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Telegram Bot API bilan yupqa qatlam — BOSQICH-7.md §5 #6.
 *
 * Provayder almashish ehtimoli yo'q (Telegram — mahsulot qarori, `ReceiptPrinter`
 * kabi fiskal talab emas), shuning uchun interfeys ortiga yashirilmagan.
 * Testlarda `Http::fake()` yetarli.
 */
final class TelegramClient
{
    public function __construct(
        private readonly ?string $token,
        private readonly string $baseUrl,
    ) {}

    /**
     * @return array<string, mixed> Telegram API javobi (`result` kaliti)
     */
    public function sendMessage(int $chatId, string $text): array
    {
        $response = Http::baseUrl($this->apiUrl())
            ->asJson()
            ->post('sendMessage', [
                'chat_id' => $chatId,
                'text' => $text,
            ]);

        if (! $response->successful() || $response->json('ok') !== true) {
            throw new RuntimeException(
                'Telegram sendMessage rad etdi: '.($response->json('description') ?? $response->status())
            );
        }

        /** @var array<string, mixed> $result */
        $result = $response->json('result', []);

        return $result;
    }

    /**
     * Webhook URL'ni Telegramga ro'yxatdan o'tkazadi (deploy vaqtida qo'lda).
     */
    public function setWebhook(string $url, string $secretToken): void
    {
        $response = Http::baseUrl($this->apiUrl())
            ->asJson()
            ->post('setWebhook', [
                'url' => $url,
                'secret_token' => $secretToken,
            ]);

        if (! $response->successful() || $response->json('ok') !== true) {
            throw new RuntimeException(
                'Telegram setWebhook rad etdi: '.($response->json('description') ?? $response->status())
            );
        }
    }

    private function apiUrl(): string
    {
        if ($this->token === null || $this->token === '') {
            throw new RuntimeException('TELEGRAM_BOT_TOKEN sozlanmagan.');
        }

        return rtrim($this->baseUrl, '/').'/bot'.$this->token.'/';
    }
}
