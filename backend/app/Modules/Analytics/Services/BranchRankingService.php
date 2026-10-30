<?php

declare(strict_types=1);

namespace App\Modules\Analytics\Services;

use App\Modules\Analytics\Support\ReportPeriod;
use App\Modules\Analytics\Support\ScopesReportsToBranch;
use App\Modules\Core\Models\User;
use App\Modules\Payroll\Enums\PlanType;
use App\Modules\Payroll\Models\BranchPlan;
use App\Modules\Sales\Models\Order;
use App\Support\Money\Money;
use Carbon\CarbonImmutable;

/**
 * Filiallar reytingi — PROJECT.md 7.18, BOSQICH-10.md §10c.
 *
 * Reyting **reja bajarilishi (%) bo'yicha**, mutlaq summa bo'yicha
 * emas — aks holda katta filial har doim g'olib chiqadi.
 */
final class BranchRankingService
{
    use ScopesReportsToBranch;

    public function __construct(private readonly CashReportService $cashReport) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function generate(User $user, string $period): array
    {
        $branchIds = $this->resolveBranchIds($user, null);

        $plans = BranchPlan::query()
            ->withoutGlobalScopes()
            ->where('period', $period)
            ->when($branchIds !== null, fn ($query) => $query->whereIn('branch_id', $branchIds))
            ->get();

        $result = $plans->map(function (BranchPlan $plan) use ($period, $user): array {
            $achieved = $this->achievedAmount($plan, $period);
            $percent = $plan->target_amount->isPositive()
                ? bcdiv(bcmul($achieved->toString(), '100', 6), $plan->target_amount->toString(), 2)
                : '0.00';

            $today = CarbonImmutable::today('Asia/Tashkent');
            $elapsed = $period < $today->format('Y-m') ? '100.00' : ($period > $today->format('Y-m') ? '0.00' : bcdiv(bcmul((string) $today->day, '100', 4), (string) $today->daysInMonth, 2));
            $cash = $this->cashReport->generate($user, $today, $today, $plan->branch_id);

            return [
                'cash_today' => $cash['net_total'],
                'month_elapsed_pct' => $elapsed,
                'branch_id' => $plan->branch_id,
                'type' => $plan->type->value,
                'target_amount' => $plan->target_amount->toString(),
                'achieved_amount' => $achieved->toString(),
                'percent' => $percent,
            ];
        })->all();

        usort($result, static fn (array $a, array $b): int => Money::of($b['percent'])->compareTo($a['percent']));

        return $result;
    }

    private function achievedAmount(BranchPlan $plan, string $period): Money
    {
        $from = CarbonImmutable::createFromFormat('Y-m-d', $period.'-01')->startOfMonth();
        $to = $from->endOfMonth();

        $orders = Order::query()
            ->withoutGlobalScopes()
            ->where('branch_id', $plan->branch_id)
            ->revenueRecognized();
        ReportPeriod::apply($orders->getQuery(), 'orders', 'orders.revenue_recognized_at', $from, $to);
        $orders = $orders->get(['total', 'cost_total']);

        return match ($plan->type) {
            PlanType::Revenue => $orders->reduce(
                static fn (Money $carry, Order $order): Money => $carry->plus($order->total),
                Money::zero(),
            ),
            PlanType::Profit => $orders->reduce(
                static fn (Money $carry, Order $order): Money => $carry->plus($order->total->minus($order->cost_total)),
                Money::zero(),
            ),
            PlanType::Orders => Money::of((string) $orders->count()),
        };
    }
}
