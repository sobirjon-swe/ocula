<?php

declare(strict_types=1);

namespace App\Modules\Analytics\Services;

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

        $result = $plans->map(function (BranchPlan $plan) use ($period): array {
            $achieved = $this->achievedAmount($plan, $period);
            $percent = $plan->target_amount->isPositive()
                ? bcdiv(bcmul($achieved->toString(), '100', 6), $plan->target_amount->toString(), 2)
                : '0.00';

            return [
                'branch_id' => $plan->branch_id,
                'type' => $plan->type->value,
                'target_amount' => $plan->target_amount->toString(),
                'achieved_amount' => $achieved->toString(),
                'percent' => $percent,
            ];
        })->all();

        usort($result, static fn (array $a, array $b): int => bccomp($b['percent'], $a['percent'], 2));

        return $result;
    }

    private function achievedAmount(BranchPlan $plan, string $period): Money
    {
        $from = CarbonImmutable::createFromFormat('Y-m-d', $period.'-01')->startOfMonth();
        $to = $from->endOfMonth();

        $orders = Order::query()
            ->withoutGlobalScopes()
            ->where('branch_id', $plan->branch_id)
            ->revenueRecognized()
            ->whereBetween('revenue_recognized_at', [$from, $to])
            ->get(['total', 'cost_total']);

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
