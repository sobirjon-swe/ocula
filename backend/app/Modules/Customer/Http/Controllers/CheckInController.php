<?php

declare(strict_types=1);

namespace App\Modules\Customer\Http\Controllers;

use App\Modules\Clinic\Actions\Visit\CreateVisit;
use App\Modules\Clinic\Enums\VisitSource;
use App\Modules\Clinic\Models\Visit;
use App\Modules\Core\Models\Branch;
use App\Modules\Customer\Http\Requests\CheckInRequest;
use App\Support\Http\CustomerApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CheckInController extends CustomerApiController
{
    public function index(Request $request): JsonResponse
    {
        $customer = $this->currentCustomer($request);
        $visits = Visit::withoutGlobalScopes()->where('customer_id', $customer->id)
            ->whereDate('queue_date', now('Asia/Tashkent')->toDateString())->open()->with('branch')->get();

        return response()->json(['data' => $visits->map(fn (Visit $visit): array => $this->card($visit))]);
    }

    public function store(CheckInRequest $request, CreateVisit $create): JsonResponse
    {
        $visit = $create->handle(null, Branch::query()->findOrFail($request->integer('branch_id')), $this->currentCustomer($request)->id, VisitSource::Qr);

        return response()->json(['data' => $this->card($visit->load('branch'))], 201);
    }

    /** @return array<string, mixed> */
    private function card(Visit $visit): array
    {
        return ['id' => $visit->id, 'branch_id' => $visit->branch_id, 'branch' => $visit->branch?->name,
            'queue_number' => $visit->queue_number, 'queue_date' => $visit->queue_date?->toDateString(), 'status' => $visit->status->value];
    }
}
