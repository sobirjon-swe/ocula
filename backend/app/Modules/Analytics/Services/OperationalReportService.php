<?php

declare(strict_types=1);

namespace App\Modules\Analytics\Services;

use App\Modules\Analytics\Support\ReportPeriod;
use App\Modules\Analytics\Support\ScopesReportsToBranch;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Core\Models\User;
use App\Modules\Warehouse\Models\Transfer;
use App\Support\Money\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class OperationalReportService
{
    use ScopesReportsToBranch;

    /** @return array<int,array<string,mixed>> */
    public function generate(string $report, User $user, CarbonImmutable $from, CarbonImmutable $to, ?int $branchId): array
    {
        $ids = $this->resolveBranchIds($user, $branchId);

        return match ($report) {
            'debt-aging' => $this->debtAging($ids),
            'turnover' => $this->turnover($ids, $from, $to),
            'abandoned-orders' => $this->abandoned($ids, $from, $to),
            'transport-costs' => $this->transport($ids, $from, $to),
            'supplier-balances' => $this->suppliers(),
            default => [],
        };
    }

    /** @param array<int,int>|null $ids */
    private function scope(Builder $query, ?array $ids, string $column = 'branch_id'): Builder
    {
        return $query->when($ids !== null, fn (Builder $q) => $q->whereIn($column, $ids));
    }

    /** @param array<int,int>|null $ids
     * @return array<int,array<string,mixed>>
     */
    private function debtAging(?array $ids): array
    {
        $today = CarbonImmutable::today('Asia/Tashkent');
        $buckets = array_fill_keys(['0-7', '8-30', '31-90', '90+'], ['count' => 0, 'remaining' => Money::zero()]);
        foreach ($this->scope(DB::table('debts'), $ids)->whereNotIn('status', ['paid', 'written_off'])->get(['due_date', 'amount', 'paid']) as $debt) {
            $late = max(0, (int) CarbonImmutable::parse($debt->due_date, 'Asia/Tashkent')->diffInDays($today, false));
            $bucket = $late <= 7 ? '0-7' : ($late <= 30 ? '8-30' : ($late <= 90 ? '31-90' : '90+'));
            $buckets[$bucket]['count']++;
            $buckets[$bucket]['remaining'] = $buckets[$bucket]['remaining']->plus(Money::of($debt->amount)->minus($debt->paid));
        }
        $rows = [];
        foreach ($buckets as $bucket => $data) {
            $rows[] = ['bucket' => $bucket, 'count' => $data['count'], 'remaining' => $data['remaining']->toString()];
        }

        return $rows;
    }

    /** @param array<int,int>|null $ids
     * @return array<int,array<string,mixed>>
     */
    private function turnover(?array $ids, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $start = CarbonImmutable::parse($from->toDateString(), 'Asia/Tashkent')->startOfDay()->utc();
        $end = CarbonImmutable::parse($to->toDateString(), 'Asia/Tashkent')->endOfDay()->utc();
        $stock = $this->scope(DB::table('stock_movements'), $ids)->where('created_at', '<=', $end)
            ->selectRaw('variant_id, SUM(CASE WHEN created_at < ? THEN quantity ELSE 0 END)::int AS opening, SUM(quantity)::int AS closing', [$start])
            ->groupBy('variant_id')->get();
        $salesQuery = $this->scope(DB::table('order_items')->join('orders', 'orders.id', '=', 'order_items.order_id'), $ids, 'orders.branch_id')
            ->where('itemable_type', ProductVariant::class)->whereNotNull('revenue_recognized_at');
        ReportPeriod::apply($salesQuery, 'orders', 'revenue_recognized_at', $from, $to);
        $sales = $salesQuery->selectRaw('itemable_id, SUM(quantity)::int AS sold')->groupBy('itemable_id')->pluck('sold', 'itemable_id');
        $returnsQuery = $this->scope(DB::table('return_items')
            ->join('returns', 'returns.id', '=', 'return_items.return_id')
            ->join('order_items', 'order_items.id', '=', 'return_items.order_item_id'), $ids, 'returns.branch_id')
            ->where('order_items.itemable_type', ProductVariant::class);
        ReportPeriod::apply($returnsQuery, 'returns', 'returns.created_at', $from, $to);
        $returned = $returnsQuery->selectRaw('order_items.itemable_id, SUM(return_items.quantity)::int AS returned')
            ->groupBy('order_items.itemable_id')->pluck('returned', 'itemable_id');
        $days = max(1, (int) $from->startOfDay()->diffInDays($to->startOfDay()) + 1);

        return $stock->map(function (object $row) use ($sales, $returned, $days): array {
            $average = bcdiv((string) ($row->opening + $row->closing), '2', 4);
            $sold = (int) ($sales->get($row->variant_id) ?? 0) - (int) ($returned->get($row->variant_id) ?? 0);

            return ['variant_id' => (int) $row->variant_id, 'opening_quantity' => $row->opening, 'closing_quantity' => $row->closing,
                'sold_quantity' => $sold, 'turnover' => bccomp($average, '0', 4) > 0 ? bcdiv((string) $sold, $average, 4) : null,
                'days_on_hand' => $sold > 0 ? bcdiv(bcmul($average, (string) $days, 4), (string) $sold, 2) : null];
        })->all();
    }

    /** @param array<int,int>|null $ids
     * @return array<int,array<string,mixed>>
     */
    private function abandoned(?array $ids, CarbonImmutable $from, CarbonImmutable $to): array
    {
        return $this->scope(DB::table('orders'), $ids)->whereNotNull('abandoned_at')
            ->whereBetween('abandoned_at', [$from->startOfDay(), $to->endOfDay()])
            ->whereNull('delivered_at')->orderBy('abandoned_at')
            ->select('id as order_id', 'number', 'branch_id', 'customer_id', 'abandoned_at')
            ->get()->map(function (object $order): array {
                $cost = DB::table('order_items')->where('order_id', $order->order_id)->sum('cost_total');

                return [...(array) $order, 'cost_loss' => Money::of((string) $cost)->toString()];
            })->all();
    }

    /** @param array<int,int>|null $ids
     * @return array<int,array<string,mixed>>
     */
    private function transport(?array $ids, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $query = DB::table('expenses')->join('transfers', 'transfers.id', '=', 'expenses.source_id')
            ->join('locations as origin', 'origin.id', '=', 'transfers.from_location_id')
            ->join('locations as destination', 'destination.id', '=', 'transfers.to_location_id')
            ->where('expenses.source_type', Transfer::class)
            ->whereBetween('expenses.date', [$from->toDateString(), $to->toDateString()]);
        $this->scope($query, $ids, 'expenses.branch_id');

        return $query->selectRaw('origin.branch_id AS from_branch_id,destination.branch_id AS to_branch_id,COUNT(*)::int AS trips,SUM(expenses.amount) AS amount')
            ->groupBy('origin.branch_id', 'destination.branch_id')->get()->map(fn (object $row): array => [
                'from_branch_id' => $row->from_branch_id, 'to_branch_id' => $row->to_branch_id, 'trips' => $row->trips, 'amount' => Money::of($row->amount)->toString(),
            ])->all();
    }

    /** @return array<int,array<string,mixed>> */
    private function suppliers(): array
    {
        return DB::table('suppliers')->leftJoin('supplier_transactions', 'supplier_transactions.supplier_id', '=', 'suppliers.id')
            ->selectRaw('suppliers.id AS supplier_id,suppliers.name,COALESCE(SUM(supplier_transactions.amount),0) AS balance,MIN(CASE WHEN supplier_transactions.amount < 0 THEN due_date END) AS oldest_due_date')
            ->groupBy('suppliers.id', 'suppliers.name')->orderBy('suppliers.name')->get()->map(fn (object $row): array => [
                'supplier_id' => $row->supplier_id, 'name' => $row->name, 'balance' => Money::of($row->balance)->toString(),
                'oldest_due_date' => Money::of($row->balance)->isNegative() ? $row->oldest_due_date : null,
            ])->all();
    }
}
