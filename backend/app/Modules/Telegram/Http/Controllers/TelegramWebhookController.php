<?php

declare(strict_types=1);

namespace App\Modules\Telegram\Http\Controllers;

use App\Modules\Telegram\Actions\LinkCustomerByCode;
use App\Modules\Telegram\Services\CustomerMessage;
use App\Modules\Telegram\Services\TelegramClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Telegramdan kelgan yangiliklar — BOSQICH-7.md §3, §5 #5.
 *
 * Ochiq yo'l (`auth:sanctum` yo'q): Telegram serveri Sanctum token bilan
 * kelmaydi. O'rniga `X-Telegram-Bot-Api-Secret-Token` sarlavhasi
 * tekshiriladi (Telegram'ning o'zi taklif qiladigan mexanizm).
 *
 * Hozircha faqat `/start <code>` tushuniladi — qolgan xabarlarga
 * javob berilmaydi (bu bosqichda bot faqat push-bildirishnoma, §1).
 */
final class TelegramWebhookController
{
    public function handle(Request $request, LinkCustomerByCode $link, TelegramClient $client): JsonResponse
    {
        $expectedSecret = config('services.telegram.webhook_secret');

        if (
            ! is_string($expectedSecret) || $expectedSecret === ''
            || ! hash_equals($expectedSecret, (string) $request->header('X-Telegram-Bot-Api-Secret-Token'))
        ) {
            abort(Response::HTTP_FORBIDDEN);
        }

        $text = (string) $request->input('message.text', '');
        $chatId = $request->input('message.chat.id');

        if (str_starts_with($text, '/start') && is_int($chatId)) {
            $this->handleStart($text, $chatId, $link, $client);
        }

        // Telegram javobning tanasiga qaramaydi — muhimi 200.
        return response()->json(['ok' => true]);
    }

    private function handleStart(string $text, int $chatId, LinkCustomerByCode $link, TelegramClient $client): void
    {
        $code = trim(substr($text, strlen('/start')));

        if ($code === '') {
            return;
        }

        $customer = $link->handle($code, $chatId);

        $message = $customer !== null
            ? CustomerMessage::for($customer, 'telegram::notification.link_success', ['name' => $customer->name])
            : trans('telegram::notification.link_failed');

        $client->sendMessage($chatId, $message);
    }
}
