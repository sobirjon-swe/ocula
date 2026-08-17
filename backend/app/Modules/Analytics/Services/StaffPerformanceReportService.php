<?php

declare(strict_types=1);

namespace App\Modules\Analytics\Services;

use App\Modules\Analytics\Support\ScopesReportsToBranch;
use App\Modules\Core\Models\User;
use App\Modules\Payroll\Models\BonusEntry;
use App\Support\Money\Money;

/**
 * Xodimlar ko'rsatkichi — PROJECT.md 6.11, BOSQICH-10.md §10c.
 *
 * `bonus_entries` dan: buyurtma soni (asl hisoblangan yozuvlar) va
 * davr uchun sof mukofot (storno hisobga olingan holda, 7.12).
 */
final class StaffPerformanceReportService
{
    use ScopesReportsToBranch;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function generate(User $user, string $period, ?int $branchId = null): array
    {
        $branchIds = $this->resolveBranchIds($user, $branchId);

        if ($branchIds === []) {
            return [];
        }

        $rows = BonusEntry::query()
            ->join('orders', 'orders.id', '=', 'bonus_entries.order_id')
            ->where('bonus_entries.period', $period)
            ->when($branchIds !== null, fn ($query) => $query->whereIn('orders.branch_id', $branchIds))
            ->selectRaw(
                'bonus_entries.user_id, '
                .'COUNT(CASE WHEN bonus_entries.reverses_id IS NULL THEN 1 END) as orders_count, '
                .'COALESCE(SUM(bonus_entries.amount), 0) as bonus_total',
            )
            ->groupBy('bonus_entries.user_id')
            ->orderByDesc('bonus_total')
            ->get();

        return $rows->map(static fn (object $row): array => [
            'user_id' => (int) $row->user_id,
            'orders_count' => (int) $row->orders_count,
            'bonus_total' => Money::of((string) $row->bonus_total)->toString(),
        ])->all();
    }
}
