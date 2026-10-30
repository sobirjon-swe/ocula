<?php

declare(strict_types=1);

namespace App\Modules\Analytics\Services;

use App\Modules\Analytics\Support\ReportPeriod;
use App\Modules\Analytics\Support\ScopesReportsToBranch;
use App\Modules\Core\Models\User;
use App\Modules\Sales\Models\Order;
use App\Support\Money\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

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

        $orders = Order::query()->withoutGlobalScopes()->revenueRecognized();
        ReportPeriod::apply($orders->getQuery(), 'orders', 'revenue_recognized_at', $from, $to);
        $orders = $orders
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

        $returnsQuery = DB::table('returns')
            ->when($branchIds !== null, fn ($q) => $q->whereIn('branch_id', $branchIds));
        ReportPeriod::apply($returnsQuery, 'returns', 'created_at', $from, $to);
        $returns = $returnsQuery->selectRaw('COALESCE(SUM(amount),0) AS amount, COALESCE(SUM(cost_total),0) AS cost')->first();
        $revenue = $revenue->minus((string) $returns->amount);
        $cost = $cost->minus((string) $returns->cost);
        $expenses = Money::of((string) DB::table('expenses')
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->when($branchIds !== null, fn ($q) => $q->whereIn('branch_id', $branchIds))->sum('amount'));
        $doubtful = Money::of((string) DB::table('debts')->where('status', 'doubtful')
            ->whereBetween('due_date', [$from->subDays((int) config('optika.debts.doubtful_after_days', 90))->toDateString(), $to->subDays((int) config('optika.debts.doubtful_after_days', 90))->toDateString()])
            ->when($branchIds !== null, fn ($q) => $q->whereIn('branch_id', $branchIds))
            ->sum(DB::raw('amount - paid')));

        return [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'orders_count' => $orders->count(),
            'revenue' => $revenue->toString(),
            'cost_total' => $cost->toString(),
            'gross_profit' => $revenue->minus($cost)->toString(),
            'expenses' => $expenses->toString(),
            'doubtful_debt' => $doubtful->toString(),
            'profit' => $revenue->minus($cost)->minus($expenses)->minus($doubtful)->toString(),
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
