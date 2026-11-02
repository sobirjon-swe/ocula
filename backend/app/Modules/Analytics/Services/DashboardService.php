<?php

declare(strict_types=1);

namespace App\Modules\Analytics\Services;

use App\Modules\Catalog\Models\Product;
use App\Modules\Core\Models\User;
use App\Support\Money\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Bosh ekran — PROJECT.md 7.8, 7.18, BOSQICH-10.md §10c.
 *
 * Majburiy qator: "Bajarilmagan buyurtmalar majburiyati" (7.8) — bu
 * qanchalik oddiy tuyulmasin, direktor har kuni ko'radigan yagona
 * raqam, shuning uchun bosh ekranda alohida ajratilgan.
 */
final class DashboardService
{
    public function __construct(
        private readonly CashReportService $cashReport,
        private readonly ProfitReportService $profitReport,
        private readonly BranchRankingService $branchRanking,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function summary(User $user, ?int $branchId = null): array
    {
        $today = CarbonImmutable::today('Asia/Tashkent');
        $cashToday = $this->cashReport->generate($user, $today, $today, $branchId);
        $outstanding = $this->profitReport->outstandingLiability($user, $branchId);
        $ranking = $this->branchRanking->generate($user, $today->format('Y-m'));
        $profit = $this->profitReport->generate($user, $today, $today, $branchId);
        $ids = $user->canAccessAllBranches() ? ($branchId ? [$branchId] : null) : array_values(array_intersect($user->accessibleBranchIds(), $branchId ? [$branchId] : $user->accessibleBranchIds()));
        $scoped = static fn (string $table) => DB::table($table)->when($ids !== null, fn ($q) => $q->whereIn('branch_id', $ids));
        $overdue = $scoped('orders')->where('due_date', '<', $today->toDateString())->whereNotIn('status', ['delivered', 'closed', 'cancelled', 'returned'])->count();
        $debt = Money::of((string) $scoped('debts')->whereNotIn('status', ['paid', 'written_off'])->sum(DB::raw('amount-paid')));
        $attention = [];
        foreach ($scoped('shifts')->where('status', 'closed')->where('difference', '<', 0)->orderByDesc('closed_at')->limit(10)->get(['id', 'branch_id', 'difference']) as $shift) {
            $attention[] = ['type' => 'cash_shortage', 'branch_id' => $shift->branch_id, 'amount' => $shift->difference, 'href' => '/shifts'];
        }
        $transfers = DB::table('transfers')->when($ids !== null, function ($q) use ($ids): void {
            $locations = DB::table('locations')->whereIn('branch_id', $ids)->select('id');
            $q->where(fn ($q) => $q->whereIn('from_location_id', $locations)->orWhereIn('to_location_id', $locations));
        })->where(fn ($q) => $q->whereIn('status', ['sent', 'in_transit'])->orWhere('has_discrepancy', true))->count();
        if ($transfers) {
            $attention[] = ['type' => 'unreceived_transfers', 'count' => $transfers, 'href' => '/transfers'];
        }
        $lateDebts = $scoped('debts')->whereIn('status', ['overdue', 'doubtful'])->count();
        if ($lateDebts) {
            $attention[] = ['type' => 'overdue_debts', 'count' => $lateDebts, 'href' => '/debts'];
        }
        if ($overdue) {
            $attention[] = ['type' => 'overdue_orders', 'count' => $overdue, 'href' => '/orders'];
        }
        $pendingProducts = Product::query()->where('status', 'pending')->whereNull('merged_into_id')->count();
        if ($pendingProducts) {
            $attention[] = ['type' => 'pending_products', 'count' => $pendingProducts, 'href' => '/products'];
        }
        foreach ($scoped('lost_sales')->where('created_at', '>=', $today->subDays(30))->groupBy('variant_id', 'branch_id')->havingRaw('COUNT(*) >= 3')->selectRaw('variant_id,branch_id,COUNT(*) AS count')->limit(10)->get() as $lost) {
            $attention[] = ['type' => 'repeated_lost_sale', 'variant_id' => $lost->variant_id, 'branch_id' => $lost->branch_id, 'count' => (int) $lost->count, 'href' => '/lost-sales'];
        }

        return [
            'date' => $today->toDateString(),
            'cash_net_today' => $cashToday['net_total'],
            'net_profit_today' => $profit['profit'],
            'total_debt' => $debt->toString(),
            'overdue_orders' => $overdue,
            'attention' => $attention,
            'pending_products' => $pendingProducts,
            'month_elapsed_percent' => bcdiv(bcmul((string) $today->day, '100', 4), (string) $today->daysInMonth, 2),
            'outstanding_liability' => $outstanding->toString(),
            'branch_ranking' => $ranking,
        ];
    }
}
