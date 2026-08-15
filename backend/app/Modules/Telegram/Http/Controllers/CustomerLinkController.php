<?php

declare(strict_types=1);

namespace App\Modules\Telegram\Http\Controllers;

use App\Modules\Sales\Models\Customer;
use App\Modules\Telegram\Actions\GenerateTelegramLinkCode;
use App\Support\Http\ApiController;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Mijozni botga bog'lash kodi — BOSQICH-7.md §3.
 *
 * Alohida ruxsat ixtiro qilinmadi: bu mijoz kartochkasini boshqarishning
 * bir qismi, shuning uchun `CustomerPolicy::update` ishlatiladi (§5 #1).
 */
final class CustomerLinkController extends ApiController
{
    public function store(Request $request, Customer $customer, GenerateTelegramLinkCode $generate): JsonResponse
    {
        $this->authorize('update', $customer);

        $linkCode = $generate->handle($this->currentUser($request), $customer);

        $botUsername = config('services.telegram.bot_username');

        return ApiResponse::created([
            'code' => $linkCode->code,
            'expires_at' => $linkCode->expires_at->toIso8601String(),
            'deep_link' => is_string($botUsername) && $botUsername !== ''
                ? "https://t.me/{$botUsername}?start={$linkCode->code}"
                : null,
        ]);
    }
}
