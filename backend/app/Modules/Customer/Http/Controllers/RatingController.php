<?php

declare(strict_types=1);

namespace App\Modules\Customer\Http\Controllers;

use App\Modules\Customer\Http\Requests\StoreRatingRequest;
use App\Modules\Sales\Models\Order;
use App\Support\Http\ApiResponse;
use App\Support\Http\CustomerApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class RatingController extends CustomerApiController
{
    public function show(Request $request, int $order): JsonResponse
    {
        $this->ownOrder($request, $order);
        $rating = DB::table('order_ratings')->where('order_id', $order)->first(['stars', 'comment']);

        return response()->json(['data' => $rating]);
    }

    public function store(StoreRatingRequest $request, int $order): JsonResponse
    {
        $model = $this->ownOrder($request, $order);
        if ($model->delivered_at === null && ! in_array($model->status->value, ['delivered', 'closed', 'returned'], true)) {
            throw ValidationException::withMessages(['order' => __('customer::profile.rating_after_delivery')]);
        }
        DB::table('order_ratings')->upsert([
            'order_id' => $order, 'customer_id' => $this->currentCustomer($request)->id,
            'stars' => $request->integer('stars'), 'comment' => $request->input('comment'),
            'created_at' => now(), 'updated_at' => now(),
        ], ['order_id'], ['stars', 'comment', 'updated_at']);

        return ApiResponse::data(['stars' => $request->integer('stars'), 'comment' => $request->input('comment')]);
    }

    private function ownOrder(Request $request, int $id): Order
    {
        $customer = $this->currentCustomer($request);
        abort_unless($customer->can('customer.order.view_own'), 403);

        return Order::query()->where('customer_id', $customer->id)->findOrFail($id);
    }
}
