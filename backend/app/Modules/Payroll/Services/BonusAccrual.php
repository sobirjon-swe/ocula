<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Services;

use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Core\Models\User;
use App\Modules\Payroll\Enums\BonusBase;
use App\Modules\Payroll\Enums\BonusStatus;
use App\Modules\Payroll\Models\BonusEntry;
use App\Modules\Payroll\Models\BonusRule;
use App\Modules\Sales\Models\Order;
use App\Modules\Sales\Models\OrderItem;
use App\Modules\Sales\Models\OrderReturnItem;
use App\Modules\Warehouse\Enums\DefectReason;
use App\Modules\Warehouse\Models\Defect;
use App\Modules\Workshop\Enums\WorkOrderStatus;
use App\Modules\Workshop\Models\WorkOrder;
use App\Support\Money\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Mukofot hisoblash — PROJECT.md 7.12, BOSQICH-10.md §10b.
 *
 * Buyurtma `closed` bo'lganda (topshirilgan VA to'liq to'langan)
 * `OrderBalance::refresh()` chaqiradi — sotuvchi, shifokor va usta
 * uchun alohida-alohida, har biri o'z qoidasi bilan.
 *
 * Yozuv **idempotent**: `bonus_entries` dagi qisman unique indeks
 * (`order_id, user_id, rule_id WHERE reverses_id IS NULL`) buyurtma
 * qayta "yopilib" qolsa ham ikki marta yozilishning oldini oladi —
 * shu tekshiruv bu yerda ham qo'llab-quvvatlanadi.
 */
final class BonusAccrual
{
    public function __construct(private readonly BonusRuleResolver $resolver) {}

    public function accrueForOrder(Order $order): void
    {
        $this->accrueForSeller($order);
        $this->accrueForDoctor($order);
        $this->accrueForMaster($order);
    }

    /** Suspend entitlement on payment reversal, restore it on full repayment. */
    public function reconcilePayment(Order $order, bool $settled): void
    {
        foreach (BonusEntry::where('order_id', $order->id)->whereNull('reverses_id')->lockForUpdate()->get() as $entry) {
            $earned = $entry->amount->plus((string) BonusEntry::where('reverses_id', $entry->id)
                ->where(fn (Builder $query) => $query->whereNull('adjustment_kind')->orWhere('adjustment_kind', '!=', 'payment'))->sum('amount'));
            $prior = Money::of((string) BonusEntry::where('reverses_id', $entry->id)->where('adjustment_kind', 'payment')->sum('amount'));
            $target = $settled ? Money::zero() : $earned->negated();
            $delta = $target->minus($prior);
            if ($delta->isZero()) {
                continue;
            }
            BonusEntry::create([
                'user_id' => $entry->user_id, 'order_id' => $entry->order_id, 'rule_id' => $entry->rule_id,
                'period' => $entry->period, 'base_amount' => $entry->base_amount->toString(),
                'percent' => $entry->percent, 'amount' => $delta->toString(), 'status' => BonusStatus::Reversed,
                'reverses_id' => $entry->id, 'adjustment_kind' => 'payment', 'calculated_at' => now(),
            ]);
        }
    }

    /**
     * Append only cumulative proportional adjustments, including recovery
     * balances for paid bonuses. Repeating the same cumulative returned
     * amount creates no additional deduction and never edits a cash payout.
     */
    public function reverseForOrder(Order $order, Money|string|null $returnedAmount = null): void
    {
        $returnedAmount = Money::of($returnedAmount ?? $order->total);
        if ($order->total->isZero()) {
            return;
        }
        BonusEntry::query()
            ->where('order_id', $order->id)
            ->whereIn('status', [BonusStatus::Accrued->value, BonusStatus::Approved->value, BonusStatus::Paid->value])
            ->whereNull('reverses_id')
            ->lockForUpdate()
            ->get()
            ->each(function (BonusEntry $entry) use ($order, $returnedAmount): void {
                $ratio = $returnedAmount->greaterThanOrEqual($order->total) ? '1' : bcdiv($returnedAmount->toString(), $order->total->toString(), 8);
                if ($entry->basis_items !== null && $entry->rule?->base !== BonusBase::Count && $order->returns()->exists()) {
                    $returnedBase = Money::zero();
                    foreach ($entry->basis_items as $basis) {
                        $returned = (int) OrderReturnItem::where('order_item_id', $basis['id'])->sum('quantity');
                        $fraction = bcdiv((string) $returned, (string) $basis['quantity'], 8);
                        $value = Money::of($basis['revenue']);
                        if ($entry->rule?->base === BonusBase::Profit) {
                            $value = $value->minus($basis['cost']);
                        }
                        $returnedBase = $returnedBase->plus($value->multipliedBy($fraction));
                    }
                    $ratio = $entry->base_amount->isPositive()
                        ? ($returnedBase->greaterThanOrEqual($entry->base_amount) ? '1' : bcdiv($returnedBase->toString(), $entry->base_amount->toString(), 8))
                        : '0';
                }
                $target = $entry->amount->multipliedBy($ratio)->negated();
                $already = Money::of((string) BonusEntry::where('reverses_id', $entry->id)
                    ->where(fn (Builder $query) => $query->whereNull('adjustment_kind')->orWhere('adjustment_kind', 'return'))->sum('amount'));
                $delta = $target->minus($already);
                if ($entry->rule?->base === BonusBase::Count) {
                    $available = $entry->amount->plus((string) BonusEntry::where('reverses_id', $entry->id)
                        ->where(fn (Builder $query) => $query->whereNull('adjustment_kind')->orWhere('adjustment_kind', '!=', 'payment'))->sum('amount'));
                    if (! $available->isPositive()) {
                        return;
                    }
                    if ($delta->isNegative() && $delta->absolute()->greaterThan($available)) {
                        $delta = $available->negated();
                    }
                }
                if (! $delta->isNegative()) {
                    return;
                }

                BonusEntry::create([
                    'user_id' => $entry->user_id,
                    'order_id' => $entry->order_id,
                    'rule_id' => $entry->rule_id,
                    'period' => $entry->period,
                    'base_amount' => $entry->base_amount->toString(),
                    'percent' => $entry->percent,
                    'amount' => $delta->toString(),
                    'status' => BonusStatus::Reversed->value,
                    'reverses_id' => $entry->id,
                    'adjustment_kind' => 'return',
                    'calculated_at' => CarbonImmutable::now(),
                ]);
            });
    }

    private function accrueForSeller(Order $order): void
    {
        $seller = $order->creator()->first();

        if ($seller instanceof User) {
            $this->accrue($order, $seller);
        }
    }

    private function accrueForDoctor(Order $order): void
    {
        if ($order->prescription_id === null) {
            return;
        }

        $doctor = $order->prescription()->first()?->doctor()->first();

        if ($doctor instanceof User) {
            $this->accrue($order, $doctor);
        }
    }

    private function accrueForMaster(Order $order): void
    {
        $workOrder = $order->workOrder()->withoutGlobalScopes()->first();

        if (! $workOrder instanceof WorkOrder
            || $workOrder->status !== WorkOrderStatus::Done
            || $workOrder->master_id === null
        ) {
            return;
        }

        $master = $workOrder->master()->first();

        if ($master instanceof User) {
            $this->accrue($order, $master);
            Defect::withoutGlobalScopes()->where('reason', DefectReason::MasterError->value)
                ->whereHas('workOrder', fn (Builder $query) => $query->where('master_id', $master->id))
                ->whereBetween('created_at', [CarbonImmutable::now('Asia/Tashkent')->startOfMonth()->utc(), CarbonImmutable::now('Asia/Tashkent')->endOfMonth()->utc()])
                ->orderBy('id')->get()->each(fn (Defect $defect) => $this->deductMasterDefect($defect));
        }
    }

    /** One defect consumes one earned count bonus, including after payout. */
    public function deductMasterDefect(Defect $defect): void
    {
        if ($defect->reason !== DefectReason::MasterError) {
            return;
        }
        $master = $defect->workOrder()->withoutGlobalScopes()->first()?->master()->first();
        if (! $master instanceof User) {
            return;
        }
        DB::transaction(function () use ($master, $defect): void {
            // Serialize the monthly pool so concurrent defects cannot consume
            // the same earned order twice.
            User::query()->lockForUpdate()->findOrFail($master->id);
            if (BonusEntry::where('defect_id', $defect->id)->exists()) {
                return;
            }
            $entries = BonusEntry::where('user_id', $master->id)
                ->where('period', $defect->created_at->setTimezone('Asia/Tashkent')->format('Y-m'))
                ->whereNull('reverses_id')->whereHas('rule', fn (Builder $query) => $query->where('base', BonusBase::Count->value))
                ->orderBy('id')->lockForUpdate()->get();
            foreach ($entries as $entry) {
                $remaining = $entry->amount->plus((string) BonusEntry::where('reverses_id', $entry->id)
                    ->where(fn (Builder $query) => $query->whereNull('adjustment_kind')->orWhere('adjustment_kind', '!=', 'payment'))->sum('amount'));
                if (! $remaining->isPositive()) {
                    continue;
                }
                BonusEntry::create([
                    'user_id' => $entry->user_id, 'order_id' => $entry->order_id,
                    'rule_id' => $entry->rule_id, 'period' => $entry->period,
                    'base_amount' => '1', 'percent' => $entry->percent,
                    'amount' => $remaining->negated()->toString(), 'status' => BonusStatus::Reversed,
                    'reverses_id' => $entry->id, 'defect_id' => $defect->id, 'calculated_at' => now(),
                    'adjustment_kind' => 'defect',
                ]);

                return;
            }
            // If no completed paid order exists yet, the defect stays pending;
            // the next master accrual retries this month's unconsumed defects.
        });
    }

    private function accrue(Order $order, User $user): void
    {
        $items = $order->items()->with('itemable')->get();
        if ($items->isEmpty()) {
            $rule = $this->resolver->resolve($user);
            if ($rule instanceof BonusRule) {
                $this->accrueRule($order, $user, $rule);
            }

            return;
        }
        $groups = [];
        foreach ($items as $item) {
            $type = $item->itemable instanceof ProductVariant
                ? $item->itemable->product()->withTrashed()->first()?->type->value
                : 'service';
            $rule = $this->resolver->resolve($user, productType: $type);
            if ($rule instanceof BonusRule) {
                $groups[$rule->id]['rule'] = $rule;
                $groups[$rule->id]['items'][] = $item;
            }
        }
        foreach ($groups as $group) {
            $this->accrueRule($order, $user, $group['rule'], $group['items']);
        }
    }

    /** @param array<int, OrderItem>|null $items */
    private function accrueRule(Order $order, User $user, BonusRule $rule, ?array $items = null): void
    {

        $exists = BonusEntry::query()
            ->where('order_id', $order->id)
            ->where('user_id', $user->id)
            ->where('rule_id', $rule->id)
            ->whereNull('reverses_id')
            ->exists();

        if ($exists) {
            return;
        }

        $period = CarbonImmutable::now('Asia/Tashkent')->format('Y-m');
        $baseAmount = $this->baseAmountFor($rule, $order, $user, $period);
        $basisItems = null;
        if ($items !== null && $rule->base !== BonusBase::Count) {
            $lineTotal = Money::zero();
            $lineCost = Money::zero();
            foreach ($items as $item) {
                $lineTotal = $lineTotal->plus($item->total);
                $lineCost = $lineCost->plus($item->cost_total);
            }
            $allLineTotal = (string) $order->items()->sum('total');
            $netRevenue = bccomp($allLineTotal, '0', 2) === 0 ? Money::zero()
                : $order->total->multipliedBy(bcdiv($lineTotal->toString(), $allLineTotal, 8));
            $baseAmount = $rule->base === BonusBase::Revenue ? $netRevenue : $netRevenue->minus($lineCost);
            $basisItems = [];
            $allocated = Money::zero();
            foreach ($items as $index => $item) {
                $revenue = $index === array_key_last($items) ? $netRevenue->minus($allocated)
                    : $order->total->multipliedBy(bcdiv($item->total->toString(), $allLineTotal, 8));
                $basisItems[] = ['id' => $item->id, 'quantity' => $item->quantity,
                    'revenue' => $revenue->toString(), 'cost' => $item->cost_total->toString()];
                $allocated = $allocated->plus($revenue);
            }
        }
        $amount = $rule->base === BonusBase::Count && $rule->rate !== null
            ? $baseAmount->multipliedBy($rule->rate)
            : $baseAmount->percentage($rule->percent);

        BonusEntry::create([
            'user_id' => $user->id,
            'order_id' => $order->id,
            'rule_id' => $rule->id,
            'period' => $period,
            'base_amount' => $baseAmount->toString(),
            'basis_items' => $basisItems,
            'percent' => $rule->percent,
            'amount' => $amount->toString(),
            'status' => BonusStatus::Accrued->value,
            'calculated_at' => CarbonImmutable::now(),
        ]);
    }

    private function baseAmountFor(BonusRule $rule, Order $order, User $user, string $period): Money
    {
        return match ($rule->base) {
            BonusBase::Revenue => $order->total,
            BonusBase::Profit => $order->total->minus($order->cost_total),
            BonusBase::Count => $this->completedCount($user, $order),
        };
    }

    /** Count each completed order once when its sale becomes fully paid. */
    private function completedCount(User $master, Order $order): Money
    {
        $completed = WorkOrder::query()
            ->withoutGlobalScopes()
            ->where('master_id', $master->id)
            ->where('order_id', $order->id)
            ->where('status', WorkOrderStatus::Done->value)
            ->count();

        return Money::of((string) $completed);
    }
}
