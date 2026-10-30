<?php

declare(strict_types=1);

namespace App\Modules\Finance\Services;

use App\Modules\Core\Enums\SettingKey;
use App\Modules\Core\Models\Setting;
use App\Modules\Finance\Enums\DebtStatus;
use App\Modules\Finance\Models\Debt;
use App\Modules\Sales\Enums\OrderStatus;
use App\Modules\Sales\Models\Customer;
use App\Modules\Sales\Models\Order;
use App\Support\Money\Money;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Qarz registrining avtomatik sinxronlanishi — PROJECT.md 7.6,
 * BOSQICH-10.md §10a.
 *
 * `Debt` qo'lda ochilmaydi: `syncForOrder()` `orders.total`/`orders.paid`
 * ning ko'zgusini yozadi (huddi ish buyrug'i buyurtma holatidan
 * tug'ilgani kabi, Bosqich 6). Qoldiq har doim `amount - paid`, bu
 * `orders.debt` bilan mos keladi.
 *
 * Holat vaqtga bog'liq (`open`→`overdue`→`doubtful`) — shuning uchun
 * `refreshStatuses()` alohida bor: to'lov hodisasisiz ham, kunlik
 * `finance:refresh-debt-statuses` orqali ham ishlaydi.
 */
final class DebtRegistry
{
    public function syncForOrder(Order $order): void
    {
        if ($order->customer_id === null) {
            return;
        }

        $debt = Debt::query()->withoutGlobalScopes()->firstWhere('order_id', $order->id);
        $payable = $order->status === OrderStatus::Cancelled
            ? Money::zero() : $order->payableTotal();

        if (! $order->debt->isPositive()) {
            if ($debt instanceof Debt && $debt->status !== DebtStatus::WrittenOff) {
                $debt->update([
                    'amount' => $payable->toString(),
                    'paid' => $order->paid->toString(),
                    'status' => DebtStatus::Paid->value,
                    'closed_at' => CarbonImmutable::now(),
                ]);
            }
            $this->syncCustomerBalance($order->customer_id);

            return;
        }

        if ($debt instanceof Debt) {
            if ($debt->status === DebtStatus::WrittenOff) {
                $this->syncCustomerBalance($order->customer_id);

                return;
            }

            $debt->update([
                'amount' => $payable->toString(),
                'paid' => $order->paid->toString(),
                'status' => $this->timeStatus($debt->due_date, CarbonImmutable::today('Asia/Tashkent'))->value,
                'closed_at' => null,
            ]);
            $this->syncCustomerBalance($order->customer_id);

            return;
        }

        $dueDate = $order->due_date ?? CarbonImmutable::today('Asia/Tashkent');

        Debt::create([
            'customer_id' => $order->customer_id,
            'order_id' => $order->id,
            'branch_id' => $order->branch_id,
            'amount' => $payable->toString(),
            'paid' => $order->paid->toString(),
            'due_date' => $dueDate->toDateString(),
            'status' => $this->timeStatus($dueDate, CarbonImmutable::today('Asia/Tashkent'))->value,
        ]);
        $this->syncCustomerBalance($order->customer_id);
    }

    public function syncCustomerBalance(int $customerId): void
    {
        $customer = Customer::query()->whereKey($customerId)->lockForUpdate()->firstOrFail();
        $balance = DB::table('debts')->where('customer_id', $customerId)
            ->whereIn('status', [DebtStatus::Open->value, DebtStatus::Overdue->value, DebtStatus::Doubtful->value])
            ->selectRaw('COALESCE(SUM(GREATEST(amount - paid, 0)), 0) AS balance')->value('balance');
        $customer->forceFill(['debt_balance' => Money::of((string) $balance)->toString()])->save();
    }

    /**
     * Kunlik buyruq chaqiradi — vaqt o'tishi bilan `open`→`overdue`→
     * `doubtful` o'tishini yangilaydi. `paid`/`written_off` tegilmaydi.
     */
    public function refreshStatuses(): int
    {
        $today = CarbonImmutable::today('Asia/Tashkent');
        $changed = 0;

        Debt::query()
            ->withoutGlobalScopes()
            ->whereIn('status', [DebtStatus::Open->value, DebtStatus::Overdue->value])
            ->chunkById(200, function ($debts) use ($today, &$changed): void {
                foreach ($debts as $debt) {
                    $target = $this->timeStatus($debt->due_date, $today);

                    if ($target !== $debt->status) {
                        $debt->update(['status' => $target->value]);
                        $changed++;
                    }
                }
            });

        return $changed;
    }

    private function timeStatus(CarbonInterface $dueDate, CarbonInterface $today): DebtStatus
    {
        if ($today->lessThanOrEqualTo($dueDate)) {
            return DebtStatus::Open;
        }

        $daysLate = $dueDate->diffInDays($today);
        $doubtfulAfterDays = (int) Setting::valueFor(SettingKey::DebtDoubtfulAfterDays);

        return $daysLate >= $doubtfulAfterDays ? DebtStatus::Doubtful : DebtStatus::Overdue;
    }
}
