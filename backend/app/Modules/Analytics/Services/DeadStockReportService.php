<?php

declare(strict_types=1);

namespace App\Modules\Analytics\Services;

use App\Modules\Analytics\Support\ScopesReportsToBranch;
use App\Modules\Core\Models\User;
use App\Modules\Warehouse\Enums\MovementType;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * O'lik zaxira — PROJECT.md 6.11, BOSQICH-10.md §10c.
 *
 * `stock_balances` "faqat hosila" (7.1) — bu yerda ham qo'shimcha
 * ustun qo'shilmaydi, `stock_movements` dan so'nggi `sale` sanasi
 * so'rov vaqtida hisoblanadi.
 */
final class DeadStockReportService
{
    use ScopesReportsToBranch;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function generate(User $user, ?int $branchId = null, int $days = 90): array
    {
        $branchIds = $this->resolveBranchIds($user, $branchId);

        if ($branchIds === []) {
            return [];
        }

        $threshold = CarbonImmutable::now()->subDays($days);

        $lastSale = DB::table('stock_movements')
            ->select('variant_id', 'location_id', DB::raw('MAX(created_at) as last_sale_at'))
            ->where('type', MovementType::Sale->value)
            ->groupBy('variant_id', 'location_id');

        $rows = DB::table('stock_balances as sb')
            ->leftJoinSub($lastSale, 'ls', function ($join): void {
                $join->on('sb.variant_id', '=', 'ls.variant_id')
                    ->on('sb.location_id', '=', 'ls.location_id');
            })
            ->where('sb.quantity', '>', 0)
            ->when($branchIds !== null, fn ($query) => $query->whereIn('sb.branch_id', $branchIds))
            ->where(function ($query) use ($threshold): void {
                $query->whereNull('ls.last_sale_at')->orWhere('ls.last_sale_at', '<', $threshold);
            })
            ->select('sb.branch_id', 'sb.variant_id', 'sb.location_id', 'sb.quantity', 'ls.last_sale_at')
            ->orderBy('ls.last_sale_at')
            ->get();

        return $rows->map(static fn (object $row): array => [
            'branch_id' => (int) $row->branch_id,
            'variant_id' => (int) $row->variant_id,
            'location_id' => (int) $row->location_id,
            'quantity' => (int) $row->quantity,
            'last_sale_at' => $row->last_sale_at,
        ])->all();
    }
}
