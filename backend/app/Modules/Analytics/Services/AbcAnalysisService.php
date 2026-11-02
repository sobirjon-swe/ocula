<?php

declare(strict_types=1);

namespace App\Modules\Analytics\Services;

use App\Modules\Analytics\Support\ReportPeriod;
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

        $query = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('product_variants as variants', 'variants.id', '=', 'order_items.itemable_id')
            ->join('products', 'products.id', '=', 'variants.product_id')
            ->join('products as canonical_products', 'canonical_products.id', '=', DB::raw('COALESCE(products.merged_into_id, products.id)'))
            ->where('order_items.itemable_type', ProductVariant::class)
            ->whereNotNull('orders.revenue_recognized_at')
            ->when($branchIds !== null, fn ($query) => $query->whereIn('orders.branch_id', $branchIds))
            ->selectRaw('COALESCE(variants.merged_into_id, variants.id) as variant_id, canonical_products.id as product_id, canonical_products.status as catalog_status, SUM(order_items.total) as revenue, SUM(order_items.quantity) as qty')
            ->groupByRaw('COALESCE(variants.merged_into_id, variants.id), canonical_products.id, canonical_products.status')
            ->orderByDesc('revenue');
        ReportPeriod::apply($query, 'orders', 'orders.revenue_recognized_at', $from, $to);
        $rows = $query->get();

        $totalRevenue = $rows->where('catalog_status', 'approved')->reduce(
            static fn (Money $carry, object $row): Money => $carry->plus((string) $row->revenue),
            Money::zero(),
        );

        $cumulative = Money::zero();
        $result = [];

        // Unchecked items remain visible, but never dilute approved ABC shares.
        foreach ($rows->sortBy(fn (object $row): int => $row->catalog_status === 'approved' ? 0 : 1) as $row) {
            $rowRevenue = Money::of((string) $row->revenue);
            $approved = $row->catalog_status === 'approved';
            if ($approved) {
                $cumulative = $cumulative->plus($rowRevenue);
            }

            $cumulativePercent = $totalRevenue->isPositive()
                ? bcdiv(bcmul($cumulative->toString(), '100', 6), $totalRevenue->toString(), 2)
                : '0.00';

            $result[] = [
                'variant_id' => (int) $row->variant_id,
                'product_id' => (int) $row->product_id,
                'catalog_status' => (string) $row->catalog_status,
                'revenue' => $rowRevenue->toString(),
                'quantity' => (int) $row->qty,
                'cumulative_percent' => $approved ? $cumulativePercent : null,
                'tier' => $approved ? $this->tierFor($cumulativePercent) : 'unchecked',
            ];
        }

        return $result;
    }

    /** @param numeric-string $cumulativePercent */
    private function tierFor(string $cumulativePercent): string
    {
        if (bccomp($cumulativePercent, '80', 2) <= 0) {
            return 'A';
        }

        return bccomp($cumulativePercent, '95', 2) <= 0 ? 'B' : 'C';
    }
}
