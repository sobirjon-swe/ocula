<?php

declare(strict_types=1);

namespace App\Modules\Analytics\Services;

use App\Modules\Analytics\Support\ScopesReportsToBranch;
use App\Modules\Core\Models\User;
use App\Support\Money\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Kassa hisoboti (7.8-A) — PERMISSIONS.md §10, BOSQICH-10.md §10c.
 *
 * "Bugun qancha pul kirdi/chiqdi" — `cash_movements` dan, toifa
 * bo'yicha ishorali yig'indi. Savdo va foyda hisobotidan (7.8-B)
 * ataylab **ajratilgan**: ular hech qachon teng bo'lmaydi (7.8).
 */
final class CashReportService
{
    use ScopesReportsToBranch;

    /**
     * @return array<string, mixed>
     */
    public function generate(User $user, CarbonImmutable $from, CarbonImmutable $to, ?int $branchId = null): array
    {
        $branchIds = $this->resolveBranchIds($user, $branchId);

        $rows = DB::table('cash_movements')
            ->whereBetween('created_at', [$from, $to->endOfDay()])
            ->when($branchIds !== null, fn ($query) => $query->whereIn('branch_id', $branchIds))
            ->selectRaw("category, COALESCE(SUM(CASE WHEN type = 'in' THEN amount ELSE -amount END), 0) AS net")
            ->groupBy('category')
            ->orderBy('category')
            ->get();

        $byCategory = $rows->map(static fn (object $row): array => [
            'category' => (string) $row->category,
            'net' => Money::of((string) $row->net)->toString(),
        ])->all();

        $total = array_reduce(
            $byCategory,
            static fn (Money $carry, array $row): Money => $carry->plus($row['net']),
            Money::zero(),
        );

        return [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'by_category' => $byCategory,
            'net_total' => $total->toString(),
        ];
    }
}
