<?php

declare(strict_types=1);

namespace App\Modules\Customer\Http\Controllers;

use App\Support\Http\ApiResponse;
use App\Support\Http\CustomerApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mijozning o'z qarzi — PROJECT.md 7.6, PERMISSIONS.md §12.
 *
 * `customers.debt_balance` kesh sifatida o'qiladi — buyurtma darajasidagi
 * qarzlar (`orders.debt`) yig'indisi bilan bir xil manba (7.6 hozircha
 * `orders.debt`/`orders.due_date` dan; to'liq `debts` registri
 * Bosqich 10).
 */
final class DebtController extends CustomerApiController
{
    public function show(Request $request): JsonResponse
    {
        $customer = $this->currentCustomer($request);

        if (! $customer->can('customer.debt.view_own')) {
            abort(Response::HTTP_FORBIDDEN);
        }

        $overdueOrders = $customer->orders()
            ->where('debt', '>', 0)
            ->whereNotNull('due_date')
            ->where('due_date', '<', now())
            ->count();

        return ApiResponse::data([
            'debt_balance' => $customer->debt_balance->toString(),
            'overdue_orders_count' => $overdueOrders,
        ]);
    }
}
