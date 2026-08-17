<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Services;

use App\Modules\Core\Models\User;
use App\Modules\Payroll\Enums\BonusBase;
use App\Modules\Payroll\Enums\BonusStatus;
use App\Modules\Payroll\Models\BonusEntry;
use App\Modules\Payroll\Models\BonusRule;
use App\Modules\Sales\Models\Order;
use App\Modules\Warehouse\Enums\DefectReason;
use App\Modules\Warehouse\Models\Defect;
use App\Modules\Workshop\Enums\WorkOrderStatus;
use App\Modules\Workshop\Models\WorkOrder;
use App\Support\Money\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

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

    /**
     * Qaytarish — mukofot storno qilinadi (7.12). Soddalashtirish:
     * qisman qaytarishda ham butun yozuv storno bo'ladi (proportsional
     * emas) — BOSQICH-10.md §10b da izohlangan.
     *
     * Allaqachon to'langan (`paid`) yozuv tegilmaydi — bu moliyaviy
     * operatsiya qaytarilmaydi.
     */
    public function reverseForOrder(Order $order): void
    {
        BonusEntry::query()
            ->where('order_id', $order->id)
            ->whereIn('status', [BonusStatus::Accrued->value, BonusStatus::Approved->value])
            ->whereNull('reverses_id')
            ->get()
            ->each(function (BonusEntry $entry): void {
                if ($entry->isReversed()) {
                    return;
                }

                BonusEntry::create([
                    'user_id' => $entry->user_id,
                    'order_id' => $entry->order_id,
                    'rule_id' => $entry->rule_id,
                    'period' => $entry->period,
                    'base_amount' => $entry->base_amount->toString(),
                    'percent' => $entry->percent,
                    'amount' => $entry->amount->negated()->toString(),
                    'status' => BonusStatus::Reversed->value,
                    'reverses_id' => $entry->id,
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
        }
    }

    private function accrue(Order $order, User $user): void
    {
        $rule = $this->resolver->resolve($user);

        if (! $rule instanceof BonusRule) {
            return;
        }

        $exists = BonusEntry::query()
            ->where('order_id', $order->id)
            ->where('user_id', $user->id)
            ->where('rule_id', $rule->id)
            ->whereNull('reverses_id')
            ->exists();

        if ($exists) {
            return;
        }

        $period = CarbonImmutable::now()->format('Y-m');
        $baseAmount = $this->baseAmountFor($rule, $order, $user, $period);
        $amount = $baseAmount->percentage($rule->percent);

        BonusEntry::create([
            'user_id' => $user->id,
            'order_id' => $order->id,
            'rule_id' => $rule->id,
            'period' => $period,
            'base_amount' => $baseAmount->toString(),
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
            BonusBase::Count => $this->netCompletedCount($user, $period),
        };
    }

    /**
     * Usta uchun: davr ichida yakunlangan (`done`) ish buyruqlari soni
     * minus o'sha davrda unga bog'langan `master_error` braklari soni
     * (7.12). Natija koeffitsient sifatida ishlatiladi — izoh
     * BOSQICH-10.md §10b da.
     */
    private function netCompletedCount(User $master, string $period): Money
    {
        $from = CarbonImmutable::createFromFormat('Y-m-d', $period.'-01')->startOfMonth();
        $to = $from->endOfMonth();

        $completed = WorkOrder::query()
            ->withoutGlobalScopes()
            ->where('master_id', $master->id)
            ->where('status', WorkOrderStatus::Done->value)
            ->whereBetween('finished_at', [$from, $to])
            ->count();

        $defects = Defect::query()
            ->withoutGlobalScopes()
            ->whereHas('workOrder', function (Builder $query) use ($master): void {
                $query->where('master_id', $master->id);
            })
            ->where('reason', DefectReason::MasterError->value)
            ->whereBetween('created_at', [$from, $to])
            ->count();

        return Money::of((string) max(0, $completed - $defects));
    }
}
