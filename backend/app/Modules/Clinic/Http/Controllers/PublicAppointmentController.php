<?php

declare(strict_types=1);

namespace App\Modules\Clinic\Http\Controllers;

use App\Modules\Clinic\Http\Requests\StorePublicAppointmentRequest;
use App\Modules\Clinic\Http\Resources\AppointmentRequestResource;
use App\Modules\Clinic\Models\AppointmentRequest;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Landing saytdan onlayn navbat so'rovi — BOSQICH-11.md.
 *
 * `auth:sanctum` dan tashqarida — mehmon hisobsiz yozadi. Real
 * navbatga tushmaydi, xodim `AppointmentRequestController` orqali
 * ko'rib chiqadi (`Clinic\Models\AppointmentRequest` docblock'i).
 */
final class PublicAppointmentController
{
    public function store(StorePublicAppointmentRequest $request): JsonResponse
    {
        // The SSR site is a shared proxy; its IP must not bucket all customers together.
        $key = 'public-appointment:'.hash('sha256', $request->string('phone')->toString());
        if (RateLimiter::tooManyAttempts($key, 3)) {
            return response()->json(['message' => __('clinic::visit.booking_rate_limited')], 429)
                ->header('Retry-After', (string) RateLimiter::availableIn($key));
        }
        RateLimiter::hit($key, 60);
        $appointment = AppointmentRequest::create($request->validated());

        return ApiResponse::created((new AppointmentRequestResource($appointment))->resolve($request));
    }
}
