<?php

declare(strict_types=1);

namespace App\Modules\Analytics\Services;

use App\Modules\Analytics\Support\ScopesReportsToBranch;
use App\Modules\Core\Models\User;
use App\Modules\Warehouse\Models\LostSale;
use Carbon\CarbonImmutable;

/**
 * Yo'qotilgan savdo hisoboti — PROJECT.md 7.9, BOSQICH-10.md §10c.
 *
 * "Oy oxirida: qaysi tovarlar doim yetishmaydi" — tovar (yoki qidiruv
 * matni) bo'yicha davr ichidagi yo'qotish soni.
 */
final class LostSaleReportService
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

        return LostSale::query()
            ->withoutGlobalScopes()
            ->whereBetween('created_at', [$from, $to->endOfDay()])
            ->when($branchIds !== null, fn ($query) => $query->whereIn('branch_id', $branchIds))
            ->selectRaw('variant_id, search_term, reason, COUNT(*) as times')
            ->groupBy('variant_id', 'search_term', 'reason')
            ->orderByDesc('times')
            ->get()
            ->map(static fn (LostSale $row): array => [
                'variant_id' => $row->variant_id,
                'search_term' => $row->search_term,
                'reason' => $row->reason->value,
                'times' => (int) $row->getAttribute('times'),
            ])
            ->all();
    }
}
