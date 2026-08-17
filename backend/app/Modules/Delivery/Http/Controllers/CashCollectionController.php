<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Http\Controllers;

use App\Modules\Core\Models\User;
use App\Modules\Delivery\Actions\Collection\CollectDriverCash;
use App\Modules\Delivery\Http\Requests\StoreCollectionRequest;
use App\Modules\Delivery\Http\Resources\CashCollectionResource;
use App\Modules\Delivery\Models\CashCollection;
use App\Support\Http\ApiController;
use App\Support\Http\ApiResponse;
use App\Support\Money\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Inkassatsiya — PROJECT.md §5.2, BOSQICH-8.md §3, §6.
 */
final class CashCollectionController extends ApiController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', CashCollection::class);

        $collections = QueryBuilder::for(CashCollection::class)
            ->allowedFilters(
                AllowedFilter::exact('driver_id'),
                AllowedFilter::exact('branch_id'),
            )
            ->allowedSorts('created_at')
            ->defaultSort('-created_at')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return CashCollectionResource::collection($collections);
    }

    public function store(StoreCollectionRequest $request, CollectDriverCash $action): JsonResponse
    {
        $this->authorize('create', CashCollection::class);

        $driver = User::query()->findOrFail($request->integer('driver_id'));

        $collection = $action->handle(
            $this->currentUser($request),
            $driver,
            $request->integer('branch_id'),
            Money::of((string) $request->input('amount')),
            shiftId: $request->integer('shift_id') ?: null,
            note: $request->string('note')->toString() ?: null,
        );

        return ApiResponse::created((new CashCollectionResource($collection))->resolve($request));
    }
}
