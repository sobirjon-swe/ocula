<?php

declare(strict_types=1);

namespace App\Modules\Telegram\Http\Controllers;

use App\Modules\Delivery\Actions\TripStop\ConfirmStopDelivery;
use App\Modules\Delivery\Actions\TripStop\DisputeStopDelivery;
use App\Modules\Delivery\Models\TripStop;
use App\Modules\Telegram\Actions\LinkCustomerByCode;
use App\Modules\Telegram\Services\CustomerMessage;
use App\Modules\Telegram\Services\TelegramClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Telegramdan kelgan yangiliklar — BOSQICH-7.md §3, §5 #5, BOSQICH-8.md §5 #6.
 *
 * Ochiq yo'l (`auth:sanctum` yo'q): Telegram serveri Sanctum token bilan
 * kelmaydi. O'rniga `X-Telegram-Bot-Api-Secret-Token` sarlavhasi
 * tekshiriladi (Telegram'ning o'zi taklif qiladigan mexanizm).
 *
 * Ikki turdagi yangilik tushuniladi: `message` (`/start <code>` —
 * bog'lash, Bosqich 7) va `callback_query` (yetkazish tasdig'i inline
 * tugmalari — Bosqich 8). Qolganlariga javob berilmaydi.
 */
final class TelegramWebhookController
{
    public function handle(
        Request $request,
        LinkCustomerByCode $link,
        TelegramClient $client,
        ConfirmStopDelivery $confirm,
        DisputeStopDelivery $dispute,
    ): JsonResponse {
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

        if ($request->filled('callback_query')) {
            $this->handleCallback($request, $client, $confirm, $dispute);
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

    /**
     * Yetkazish tasdig'i tugmalari — `delivery:confirm:{id}` /
     * `delivery:dispute:{id}` (BOSQICH-8.md §4).
     */
    private function handleCallback(
        Request $request,
        TelegramClient $client,
        ConfirmStopDelivery $confirm,
        DisputeStopDelivery $dispute,
    ): void {
        $callbackId = (string) $request->input('callback_query.id');
        $data = (string) $request->input('callback_query.data', '');

        $parts = explode(':', $data);

        if (count($parts) !== 3 || $parts[0] !== 'delivery') {
            return;
        }

        [, $action, $stopId] = $parts;

        $stop = TripStop::query()->find((int) $stopId);

        if (! $stop instanceof TripStop) {
            return;
        }

        $replyKey = match ($action) {
            'confirm' => 'telegram::notification.delivery_confirmed_reply',
            'dispute' => 'telegram::notification.delivery_disputed_reply',
            default => null,
        };

        if ($replyKey === null) {
            return;
        }

        match ($action) {
            'confirm' => $confirm->handle($stop),
            'dispute' => $dispute->handle($stop),
        };

        if ($callbackId !== '') {
            $client->answerCallbackQuery($callbackId, trans($replyKey));
        }
    }
}
