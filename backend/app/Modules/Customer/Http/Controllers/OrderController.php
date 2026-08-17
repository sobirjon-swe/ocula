<?php

declare(strict_types=1);

namespace App\Modules\Customer\Http\Controllers;

use App\Modules\Customer\Http\Resources\CustomerOrderResource;
use App\Modules\Sales\Models\Order;
use App\Support\Http\CustomerApiController;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mijozning o'z buyurtmalari — PERMISSIONS.md §12
 * (`customer.order.view_own`).
 *
 * Begona buyurtma **404**, 403 emas — mavjudligini ham bildirmaslik
 * kerak (BOSQICH-9.md §5 #1).
 */
final class OrderController extends CustomerApiController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $customer = $this->currentCustomer($request);

        if (! $customer->can('customer.order.view_own')) {
            abort(Response::HTTP_FORBIDDEN);
        }

        $orders = Order::query()
            ->where('customer_id', $customer->id)
            ->orderByDesc('created_at')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return CustomerOrderResource::collection($orders);
    }

    public function show(Request $request, int $order): CustomerOrderResource
    {
        $customer = $this->currentCustomer($request);

        if (! $customer->can('customer.order.view_own')) {
            abort(Response::HTTP_FORBIDDEN);
        }

        $model = Order::query()
            ->where('customer_id', $customer->id)
            ->findOrFail($order);

        return new CustomerOrderResource($model);
    }
}
