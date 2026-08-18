<?php

declare(strict_types=1);

namespace App\Modules\Clinic\Http\Controllers;

use App\Modules\Clinic\Http\Requests\UpdateAppointmentRequestStatusRequest;
use App\Modules\Clinic\Http\Resources\AppointmentRequestResource;
use App\Modules\Clinic\Models\AppointmentRequest;
use App\Support\Http\ApiController;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Onlayn navbat so'rovlari — xodim tomoni — BOSQICH-11.md.
 */
final class AppointmentRequestController extends ApiController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', AppointmentRequest::class);

        $requests = QueryBuilder::for(AppointmentRequest::class)
            ->allowedFilters(
                AllowedFilter::exact('branch_id'),
                AllowedFilter::exact('status'),
            )
            ->allowedSorts('created_at', 'preferred_date')
            ->defaultSort('-created_at')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return AppointmentRequestResource::collection($requests);
    }

    public function updateStatus(
        UpdateAppointmentRequestStatusRequest $request,
        AppointmentRequest $appointmentRequest,
    ): JsonResponse {
        $this->authorize('manage', $appointmentRequest);

        $appointmentRequest->update([
            'status' => $request->string('status')->toString(),
            'handled_by' => $this->currentUser($request)->id,
            'handled_at' => now(),
        ]);

        return ApiResponse::data((new AppointmentRequestResource($appointmentRequest))->resolve($request));
    }
}
