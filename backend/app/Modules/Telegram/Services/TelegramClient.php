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
     * @param  array<string, mixed>|null  $replyMarkup  Masalan, inline tugmalar
     *                                                  (BOSQICH-8.md §4): `['inline_keyboard' => [[...]]]`.
     * @return array<string, mixed> Telegram API javobi (`result` kaliti)
     */
    public function sendMessage(int $chatId, string $text, ?array $replyMarkup = null): array
    {
        $payload = ['chat_id' => $chatId, 'text' => $text];

        if ($replyMarkup !== null) {
            $payload['reply_markup'] = $replyMarkup;
        }

        $response = Http::baseUrl($this->apiUrl())
            ->asJson()
            ->post('sendMessage', $payload);

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
     * Inline tugma bosilgandan keyingi "yuklanmoqda" belgisini olib
     * tashlaydi — BOSQICH-8.md §5 #6.
     */
    public function answerCallbackQuery(string $callbackQueryId, ?string $text = null): void
    {
        Http::baseUrl($this->apiUrl())
            ->asJson()
            ->post('answerCallbackQuery', array_filter([
                'callback_query_id' => $callbackQueryId,
                'text' => $text,
            ], static fn (mixed $value): bool => $value !== null));
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
