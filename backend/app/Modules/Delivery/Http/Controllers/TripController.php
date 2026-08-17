<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Http\Controllers;

use App\Modules\Core\Models\User;
use App\Modules\Delivery\Actions\Trip\AddTripStop;
use App\Modules\Delivery\Actions\Trip\CancelTrip;
use App\Modules\Delivery\Actions\Trip\CreateTrip;
use App\Modules\Delivery\Actions\Trip\FinishTrip;
use App\Modules\Delivery\Actions\Trip\StartTrip;
use App\Modules\Delivery\Enums\TripStopType;
use App\Modules\Delivery\Http\Requests\AddTripStopRequest;
use App\Modules\Delivery\Http\Requests\StoreTripRequest;
use App\Modules\Delivery\Http\Resources\TripResource;
use App\Modules\Delivery\Http\Resources\TripStopResource;
use App\Modules\Delivery\Models\Trip;
use App\Modules\Sales\Models\Order;
use App\Modules\Warehouse\Models\Transfer;
use App\Support\Http\ApiController;
use App\Support\Http\ApiResponse;
use App\Support\Money\Money;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Yo'l varaqasi — PROJECT.md §6.7, BOSQICH-8.md §6.
 */
final class TripController extends ApiController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Trip::class);

        $trips = QueryBuilder::for(Trip::class)
            ->allowedFilters(
                AllowedFilter::exact('driver_id'),
                AllowedFilter::exact('status'),
                AllowedFilter::exact('date'),
            )
            ->allowedSorts('date', 'created_at')
            ->defaultSort('-date')
            ->with('stops')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return TripResource::collection($trips);
    }

    public function show(Trip $trip): TripResource
    {
        $this->authorize('view', $trip);

        return new TripResource($trip->load('stops'));
    }

    public function store(StoreTripRequest $request, CreateTrip $action): JsonResponse
    {
        $this->authorize('create', Trip::class);

        $driver = User::query()->findOrFail($request->integer('driver_id'));

        $trip = $action->handle(
            $this->currentUser($request),
            $driver,
            CarbonImmutable::parse($request->string('date')->toString()),
        );

        return ApiResponse::created((new TripResource($trip))->resolve($request));
    }

    public function addStop(AddTripStopRequest $request, Trip $trip, AddTripStop $action): JsonResponse
    {
        $this->authorize('create', Trip::class);

        $type = TripStopType::from($request->string('type')->toString());

        $stop = $action->handle(
            $this->currentUser($request),
            $trip,
            $type,
            transfer: $request->integer('transfer_id') ? Transfer::query()->find($request->integer('transfer_id')) : null,
            order: $request->integer('order_id') ? Order::query()->find($request->integer('order_id')) : null,
            address: $request->string('address')->toString() ?: null,
            lat: $request->input('lat') !== null ? (string) $request->input('lat') : null,
            lng: $request->input('lng') !== null ? (string) $request->input('lng') : null,
            cashToCollect: $request->input('cash_to_collect') !== null
                ? Money::of((string) $request->input('cash_to_collect'))
                : null,
        );

        return ApiResponse::created((new TripStopResource($stop))->resolve($request));
    }

    public function start(Trip $trip, StartTrip $action): TripResource
    {
        $this->authorize('start', $trip);

        return new TripResource($action->handle($trip));
    }

    public function finish(Trip $trip, FinishTrip $action): TripResource
    {
        $this->authorize('finish', $trip);

        return new TripResource($action->handle($trip));
    }

    public function cancel(Trip $trip, CancelTrip $action): TripResource
    {
        $this->authorize('cancel', $trip);

        return new TripResource($action->handle($trip));
    }
}
