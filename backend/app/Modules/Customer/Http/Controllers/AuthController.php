<?php

declare(strict_types=1);

namespace App\Modules\Customer\Http\Controllers;

use App\Modules\Customer\Actions\AuthenticateViaTelegram;
use App\Modules\Customer\Http\Requests\TelegramAuthRequest;
use App\Modules\Customer\Http\Resources\CustomerProfileResource;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mijoz kirishi — Telegram `initData`, BOSQICH-9.md §3.
 *
 * Ochiq yo'l: bu orqali kirish olinadi, `auth:customer` ostida emas.
 */
final class AuthController
{
    public function telegram(TelegramAuthRequest $request, AuthenticateViaTelegram $action): JsonResponse
    {
        $result = $action->handle($request->string('init_data')->toString());

        if ($result === null) {
            abort(Response::HTTP_UNAUTHORIZED, __('customer::auth.invalid_init_data'));
        }

        return ApiResponse::data([
            'token' => $result['token'],
            'customer' => (new CustomerProfileResource($result['customer']))->resolve($request),
        ]);
    }
}
