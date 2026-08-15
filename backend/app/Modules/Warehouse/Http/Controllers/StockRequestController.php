<?php

declare(strict_types=1);

namespace App\Modules\Warehouse\Http\Controllers;

use App\Modules\Warehouse\Actions\StockRequest\CreateStockRequest;
use App\Modules\Warehouse\Actions\StockRequest\DecideStockRequest;
use App\Modules\Warehouse\Actions\StockRequest\FulfillStockRequest;
use App\Modules\Warehouse\Http\Requests\StockRequest\StoreStockRequestRequest;
use App\Modules\Warehouse\Http\Resources\StockRequestResource;
use App\Modules\Warehouse\Http\Resources\TransferResource;
use App\Modules\Warehouse\Models\StockRequest;
use App\Support\Http\ApiController;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Ichki so'rovlar — PROJECT.md §11 (Bosqich 4).
 *
 *     pending → approved → fulfilled
 *             → rejected
 *     pending → cancelled
 *
 * Tasdiqlash **tovar chiqadigan** filialning qarori: tovar ularning
 * omborida turibdi va ular uni o'z mijozlari uchun ham kerakligini
 * biladi.
 */
final class StockRequestController extends ApiController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', StockRequest::class);

        $requests = QueryBuilder::for(StockRequest::class)
            ->allowedFilters(
                AllowedFilter::exact('status'),
                AllowedFilter::exact('from_branch_id'),
                AllowedFilter::exact('to_branch_id'),
                AllowedFilter::exact('variant_id'),
                AllowedFilter::exact('order_id'),
            )
            ->allowedSorts('created_at', 'quantity')
            ->defaultSort('-created_at', '-id')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return StockRequestResource::collection($requests);
    }

    public function show(StockRequest $stockRequest): StockRequestResource
    {
        $this->authorize('view', $stockRequest);

        return new StockRequestResource($stockRequest->load('variant'));
    }

    public function store(StoreStockRequestRequest $request, CreateStockRequest $create): JsonResponse
    {
        $this->authorize('create', StockRequest::class);

        $actor = $this->currentUser($request);
        $fromBranchId = $actor->branch_id;

        // So'rov xodimning o'z filiali nomidan yoziladi. Filialsiz
        // xodim (direktor) qaysi filial uchun so'rayotganini bilmaymiz.
        if ($fromBranchId === null) {
            throw ValidationException::withMessages([
                'from_branch_id' => __('warehouse::request.no_own_branch'),
            ]);
        }

        $stockRequest = $create->handle(
            $actor,
            $fromBranchId,
            $request->integer('to_branch_id'),
            $request->integer('variant_id'),
            $request->integer('quantity'),
            $request->integer('order_id') ?: null,
        );

        return ApiResponse::created(
            (new StockRequestResource($stockRequest))->resolve($request),
        );
    }

    public function approve(
        Request $request,
        StockRequest $stockRequest,
        DecideStockRequest $decide,
    ): StockRequestResource {
        $this->authorize('approve', $stockRequest);

        return new StockRequestResource(
            $decide->approve($this->currentUser($request), $stockRequest),
        );
    }

    public function reject(
        Request $request,
        StockRequest $stockRequest,
        DecideStockRequest $decide,
    ): StockRequestResource {
        $this->authorize('approve', $stockRequest);

        return new StockRequestResource(
            $decide->reject($this->currentUser($request), $stockRequest),
        );
    }

    public function cancel(StockRequest $stockRequest, DecideStockRequest $decide): StockRequestResource
    {
        $this->authorize('cancel', $stockRequest);

        return new StockRequestResource($decide->cancel($stockRequest));
    }

    /**
     * So'rovdan transfer yaratadi — hujjat qoralama bo'lib tug'iladi,
     * jo'natish alohida ruxsat talab qiladi.
     */
    public function fulfill(
        Request $request,
        StockRequest $stockRequest,
        FulfillStockRequest $fulfill,
    ): JsonResponse {
        $this->authorize('fulfill', $stockRequest);

        $transfer = $fulfill->handle($this->currentUser($request), $stockRequest);

        return ApiResponse::created(
            (new TransferResource($transfer->load('items')))->resolve($request),
        );
    }
}
