<?php

declare(strict_types=1);

namespace App\Modules\Sales\Http\Controllers;

use App\Modules\Sales\Actions\OrderReturn\CreateReturn;
use App\Modules\Sales\Enums\PaymentMethod;
use App\Modules\Sales\Enums\ReturnReason;
use App\Modules\Sales\Http\Requests\OrderReturn\StoreReturnRequest;
use App\Modules\Sales\Http\Resources\OrderReturnResource;
use App\Modules\Sales\Models\Order;
use App\Modules\Sales\Models\OrderReturn;
use App\Support\Http\ApiController;
use App\Support\Http\ApiResponse;
use App\Support\Money\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Qaytarishlar — SCHEMA.md §4 (3.6), PROJECT.md 7.20, 7.21.
 *
 * Hujjat ombor va kassa harakatlarini tug'diradi, shuning uchun u
 * tahrirlanmaydi va o'chirilmaydi — xato bo'lsa harakatlar alohida
 * storno qilinadi.
 */
final class OrderReturnController extends ApiController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', OrderReturn::class);

        $returns = QueryBuilder::for(OrderReturn::class)
            ->allowedFilters(
                AllowedFilter::exact('order_id'),
                AllowedFilter::exact('branch_id'),
                AllowedFilter::exact('shift_id'),
                AllowedFilter::exact('reason'),
                AllowedFilter::partial('number'),
            )
            ->allowedSorts('created_at', 'amount')
            ->defaultSort('-created_at', '-id')
            ->withCount('items')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return OrderReturnResource::collection($returns);
    }

    public function show(OrderReturn $return): OrderReturnResource
    {
        $this->authorize('view', $return);

        return new OrderReturnResource($return->load('items'));
    }

    public function store(
        StoreReturnRequest $request,
        Order $order,
        CreateReturn $create,
    ): JsonResponse {
        $this->authorize('create', OrderReturn::class);
        $this->authorize('view', $order);

        $refund = $request->string('refund')->toString();

        $return = $create->handle(
            $this->currentUser($request),
            $order,
            ReturnReason::from($request->string('reason')->toString()),
            $this->items($request),
            $refund === '' ? null : Money::of($refund),
            PaymentMethod::from($request->string('refund_method')->toString() ?: 'cash'),
        );

        return ApiResponse::created(
            (new OrderReturnResource($return->load('items')))->resolve($request),
        );
    }

    /**
     * @return array<int, array{order_item_id: int, quantity: int, restock?: bool}>
     */
    private function items(StoreReturnRequest $request): array
    {
        /** @var array<int, array<string, mixed>> $rows */
        $rows = $request->array('items');

        return array_map(
            static fn (array $row): array => [
                'order_item_id' => (int) $row['order_item_id'],
                'quantity' => (int) $row['quantity'],
                'restock' => (bool) ($row['restock'] ?? true),
            ],
            $rows,
        );
    }
}
