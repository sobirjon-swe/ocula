<?php

declare(strict_types=1);

namespace App\Modules\Sales\Services;

use App\Modules\Core\Models\User;
use App\Modules\Sales\Models\Customer;
use App\Modules\Sales\Models\Order;
use App\Support\Money\Money;
use Illuminate\Validation\ValidationException;

final class CreditApproval
{
    public function exposure(Order $order): Money
    {
        // Serialize handovers for a customer; two concurrent sales cannot each
        // independently spend the same remaining credit allowance.
        if ($order->customer_id !== null) {
            Customer::query()->lockForUpdate()->findOrFail($order->customer_id);
        }
        $existing = $order->customer_id === null ? '0' : Order::withoutGlobalScopes()
            ->where('customer_id', $order->customer_id)->where('id', '!=', $order->id)
            ->whereIn('status', ['delivered', 'closed'])->sum('debt');

        return Money::of((string) $existing)->plus($order->payableTotal()->minus($order->paidAmount()));
    }

    public function assertAllowed(User $actor, Order $order): void
    {
        if (! $order->payableTotal()->minus($order->paidAmount())->isPositive()) {
            return;
        }
        if ($order->customer_id === null) {
            throw ValidationException::withMessages(['customer_id' => __('sales::order.credit_customer_required')]);
        }
        $exposure = $this->exposure($order);
        if ($order->debt_approved_by !== null && $order->debt_approved_amount !== null && Money::of($order->debt_approved_amount)->greaterThanOrEqual($exposure)) {
            return;
        }
        // Handover executes the original seller's credit agreement. A manager
        // or driver cannot substitute their own allowance for that seller's.
        $seller = $order->creator()->firstOrFail();
        if (! $seller->can('sales.customer.debt.grant') || $exposure->greaterThan($seller->debt_limit)) {
            throw ValidationException::withMessages(['debt' => __('sales::order.credit_approval_required')]);
        }
    }
}
