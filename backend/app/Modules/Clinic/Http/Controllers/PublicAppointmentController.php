<?php

declare(strict_types=1);

namespace App\Modules\Clinic\Http\Controllers;

use App\Modules\Clinic\Http\Requests\StorePublicAppointmentRequest;
use App\Modules\Clinic\Http\Resources\AppointmentRequestResource;
use App\Modules\Clinic\Models\AppointmentRequest;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;

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
        $appointment = AppointmentRequest::create($request->validated());

        return ApiResponse::created((new AppointmentRequestResource($appointment))->resolve($request));
    }
}
