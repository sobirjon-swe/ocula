<?php

declare(strict_types=1);

namespace App\Modules\Analytics\Services;

use App\Modules\Analytics\Support\ScopesReportsToBranch;
use App\Modules\Core\Models\User;
use App\Modules\Sales\Models\Order;
use App\Support\Money\Money;
use Carbon\CarbonImmutable;

/**
 * Savdo va foyda hisoboti (7.8-B) — PERMISSIONS.md §10, BOSQICH-10.md §10c.
 *
 * `orders.revenue_recognized_at` bo'yicha — `created_at` emas: kechagi
 * buyurtma bugun topshirilsa, daromad bugunga tushadi (7.8).
 */
final class ProfitReportService
{
    use ScopesReportsToBranch;

    /**
     * @return array<string, mixed>
     */
    public function generate(User $user, CarbonImmutable $from, CarbonImmutable $to, ?int $branchId = null): array
    {
        $branchIds = $this->resolveBranchIds($user, $branchId);

        $orders = Order::query()
            ->withoutGlobalScopes()
            ->revenueRecognized()
            ->whereBetween('revenue_recognized_at', [$from, $to->endOfDay()])
            ->when($branchIds !== null, fn ($query) => $query->whereIn('branch_id', $branchIds))
            ->get(['total', 'cost_total']);

        $revenue = $orders->reduce(
            static fn (Money $carry, Order $order): Money => $carry->plus($order->total),
            Money::zero(),
        );
        $cost = $orders->reduce(
            static fn (Money $carry, Order $order): Money => $carry->plus($order->cost_total),
            Money::zero(),
        );

        return [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'orders_count' => $orders->count(),
            'revenue' => $revenue->toString(),
            'cost_total' => $cost->toString(),
            'profit' => $revenue->minus($cost)->toString(),
        ];
    }

    /**
     * "Bajarilmagan buyurtmalar majburiyati" (7.8) — mijozdan yig'ilgan
     * pul, lekin tovar hali topshirilmagan.
     */
    public function outstandingLiability(User $user, ?int $branchId = null): Money
    {
        $branchIds = $this->resolveBranchIds($user, $branchId);

        $orders = Order::query()
            ->withoutGlobalScopes()
            ->outstanding()
            ->when($branchIds !== null, fn ($query) => $query->whereIn('branch_id', $branchIds))
            ->get(['paid']);

        return $orders->reduce(
            static fn (Money $carry, Order $order): Money => $carry->plus($order->paid),
            Money::zero(),
        );
    }
}
