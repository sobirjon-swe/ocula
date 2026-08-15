<?php

declare(strict_types=1);

namespace App\Modules\Sales\Http\Controllers;

use App\Modules\Core\Models\Branch;
use App\Modules\Sales\Actions\Order\CancelOrder;
use App\Modules\Sales\Actions\Order\ChangeOrderStatus;
use App\Modules\Sales\Actions\Order\CreateOrder;
use App\Modules\Sales\Actions\Order\DeliverOrder;
use App\Modules\Sales\Enums\OrderDeliveryType;
use App\Modules\Sales\Enums\OrderStatus;
use App\Modules\Sales\Enums\OrderType;
use App\Modules\Sales\Http\Requests\Order\ChangeOrderStatusRequest;
use App\Modules\Sales\Http\Requests\Order\StoreOrderRequest;
use App\Modules\Sales\Http\Resources\OrderResource;
use App\Modules\Sales\Models\Order;
use App\Support\Http\ApiController;
use App\Support\Http\ApiResponse;
use App\Support\Money\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Chek va buyurtmalar — PROJECT.md 7.3, 7.8.
 *
 * Ombor va pulga tegadigan uchta amal alohida endpoint: `deliver`,
 * `cancel` va (boshqa kontrollerda) `returns`. Holat o'qini qo'lda
 * siljitish esa `status` — u hech narsaga tegmaydi.
 *
 * Ro'yxat `BranchScope` bilan avtomatik cheklanadi.
 */
final class OrderController extends ApiController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Order::class);

        $orders = QueryBuilder::for(Order::class)
            ->allowedFilters(
                AllowedFilter::exact('branch_id'),
                AllowedFilter::exact('customer_id'),
                AllowedFilter::exact('shift_id'),
                AllowedFilter::exact('type'),
                AllowedFilter::exact('status'),
                AllowedFilter::exact('payment_status'),
                AllowedFilter::partial('number'),
            )
            ->allowedSorts('created_at', 'number', 'total', 'delivered_at')
            ->defaultSort('-created_at', '-id')
            ->with('customer')
            ->withCount('items')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return OrderResource::collection($orders);
    }

    public function show(Order $order): OrderResource
    {
        $this->authorize('view', $order);

        return new OrderResource($order->load(['customer', 'items', 'payments']));
    }

    public function store(StoreOrderRequest $request, CreateOrder $create): JsonResponse
    {
        $this->authorize('create', Order::class);

        $actor = $this->currentUser($request);
        $branch = Branch::findOrFail($request->integer('branch_id'));

        if (! $actor->canAccessAllBranches() && ! in_array($branch->id, $actor->accessibleBranchIds(), true)) {
            throw ValidationException::withMessages([
                'branch_id' => __('core::shift.foreign_branch'),
            ]);
        }

        $order = $create->handle(
            $actor,
            $branch,
            OrderType::from($request->string('type')->toString()),
            $this->items($request),
            $request->integer('customer_id') ?: null,
            Money::of($request->string('discount')->toString() ?: '0'),
            OrderDeliveryType::from($request->string('delivery_type')->toString() ?: 'pickup'),
            $request->string('due_date')->toString() ?: null,
            $request->integer('prescription_id') ?: null,
        );

        return ApiResponse::created(
            (new OrderResource($order->load(['customer', 'items'])))->resolve($request),
        );
    }

    /**
     * Topshirish — tovar shu paytda ombordan chiqadi va daromad tan
     * olinadi (7.8).
     */
    public function deliver(Request $request, Order $order, DeliverOrder $deliver): OrderResource
    {
        $this->authorize('deliver', $order);

        $delivered = $deliver->handle($this->currentUser($request), $order);

        return new OrderResource($delivered->load(['customer', 'items']));
    }

    /**
     * Holat o'qini bir qadam siljitish (7.3) — ombor va pul tegilmaydi.
     */
    public function changeStatus(
        ChangeOrderStatusRequest $request,
        Order $order,
        ChangeOrderStatus $change,
    ): OrderResource {
        $this->authorize('changeStatus', $order);

        $updated = $change->handle(
            $this->currentUser($request),
            $order,
            OrderStatus::from($request->string('status')->toString()),
        );

        return new OrderResource($updated->load('items'));
    }

    public function cancel(Order $order, CancelOrder $cancel): OrderResource
    {
        $this->authorize('cancel', $order);

        return new OrderResource($cancel->handle($order));
    }

    /**
     * So'rovdagi satrlarni Action kutgan shaklga keltiradi.
     *
     * @return array<int, array{kind: string, id: int, quantity: int, discount?: string, cost_total?: string, custom_lens_params?: array<string, mixed>|null}>
     */
    private function items(StoreOrderRequest $request): array
    {
        /** @var array<int, array<string, mixed>> $rows */
        $rows = $request->array('items');

        return array_map(
            static fn (array $row): array => array_filter([
                'kind' => (string) $row['kind'],
                'id' => (int) $row['id'],
                'quantity' => (int) $row['quantity'],
                'discount' => isset($row['discount']) ? (string) $row['discount'] : null,
                'cost_total' => isset($row['cost_total']) ? (string) $row['cost_total'] : null,
                'custom_lens_params' => $row['custom_lens_params'] ?? null,
            ], static fn (mixed $value): bool => $value !== null),
            $rows,
        );
    }
}
