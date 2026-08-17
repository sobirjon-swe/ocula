<?php

declare(strict_types=1);

namespace App\Modules\Analytics\Services;

use App\Modules\Analytics\Support\ScopesReportsToBranch;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Core\Models\User;
use App\Support\Money\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * ABC tahlil — PROJECT.md 6.11, BOSQICH-10.md §10c.
 *
 * Tovarlarning 20% i daromadning 80% ini beradi: daromad bo'yicha
 * kamayish tartibida saralanadi, kumulyativ % 80 gacha — **A**, 95
 * gacha — **B**, qolgani — **C**.
 */
final class AbcAnalysisService
{
    use ScopesReportsToBranch;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function generate(User $user, CarbonImmutable $from, CarbonImmutable $to, ?int $branchId = null): array
    {
        $branchIds = $this->resolveBranchIds($user, $branchId);

        if ($branchIds === []) {
            return [];
        }

        $rows = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('order_items.itemable_type', ProductVariant::class)
            ->whereNotNull('orders.revenue_recognized_at')
            ->whereBetween('orders.revenue_recognized_at', [$from, $to->endOfDay()])
            ->when($branchIds !== null, fn ($query) => $query->whereIn('orders.branch_id', $branchIds))
            ->selectRaw('order_items.itemable_id as variant_id, SUM(order_items.total) as revenue, SUM(order_items.quantity) as qty')
            ->groupBy('order_items.itemable_id')
            ->orderByDesc('revenue')
            ->get();

        $totalRevenue = $rows->reduce(
            static fn (Money $carry, object $row): Money => $carry->plus((string) $row->revenue),
            Money::zero(),
        );

        $cumulative = Money::zero();
        $result = [];

        foreach ($rows as $row) {
            $rowRevenue = Money::of((string) $row->revenue);
            $cumulative = $cumulative->plus($rowRevenue);

            $cumulativePercent = $totalRevenue->isPositive()
                ? bcdiv(bcmul($cumulative->toString(), '100', 6), $totalRevenue->toString(), 2)
                : '0.00';

            $result[] = [
                'variant_id' => (int) $row->variant_id,
                'revenue' => $rowRevenue->toString(),
                'quantity' => (int) $row->qty,
                'cumulative_percent' => $cumulativePercent,
                'tier' => $this->tierFor($cumulativePercent),
            ];
        }

        return $result;
    }

    private function tierFor(string $cumulativePercent): string
    {
        if (bccomp($cumulativePercent, '80', 2) <= 0) {
            return 'A';
        }

        return bccomp($cumulativePercent, '95', 2) <= 0 ? 'B' : 'C';
    }
}
