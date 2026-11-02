<?php

declare(strict_types=1);

namespace App\Modules\Sales\Actions\Payment;

use App\Modules\Core\Models\Shift;
use App\Modules\Core\Models\User;
use App\Modules\Delivery\Models\CashCollection;
use App\Modules\Delivery\Services\DriverCashLedger;
use App\Modules\Finance\Models\CashMovement;
use App\Modules\Finance\Services\CashRegister;
use App\Modules\Sales\Enums\PaymentTxStatus;
use App\Modules\Sales\Models\Order;
use App\Modules\Sales\Models\Payment;
use App\Modules\Sales\Services\OrderBalance;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * To'lov stornosi — PROJECT.md 7.21.
 *
 * Asl yozuv **o'chirilmaydi va tahrirlanmaydi**: manfiy summali yangi
 * to'lov yoziladi va `reverses_id` orqali aslga bog'lanadi. Naqd
 * to'lov bo'lgan bo'lsa, kassa yozuvi ham xuddi shunday teskari
 * qilinadi — ikkalasi bir tranzaksiyada, aks holda pul daftarda
 * qolib, buyurtmada yo'q bo'lib ketardi.
 *
 * Bu amal direktor uchun (`sales.payment.reverse`): kassir o'z xatosini
 * o'zi yashira olmasligi kerak.
 */
final class ReversePayment
{
    public function __construct(
        private readonly CashRegister $cash,
        private readonly OrderBalance $balance,
    ) {}

    public function handle(User $author, Payment $payment, string $reason): Payment
    {
        if ($payment->reverses_id !== null) {
            throw ValidationException::withMessages([
                'payment' => __('sales::payment.reverse_of_reversal'),
            ]);
        }

        if ($payment->isReversed()) {
            throw ValidationException::withMessages([
                'payment' => __('sales::payment.already_reversed'),
            ]);
        }

        return DB::transaction(function () use ($author, $payment, $reason): Payment {
            Order::query()->lockForUpdate()->findOrFail($payment->order_id);
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            if ($payment->isReversed()) {
                throw ValidationException::withMessages(['payment' => __('sales::payment.already_reversed')]);
            }
            $this->reverseUncollectedFieldCash($payment);
            $reversal = Payment::create([
                'order_id' => $payment->order_id,
                'customer_id' => $payment->customer_id,
                'branch_id' => $payment->branch_id,
                'shift_id' => Shift::withoutGlobalScopes()->where('branch_id', $payment->branch_id)->active()->latest('opened_at')->value('id'),
                'amount' => $payment->amount->negated()->toString(),
                'method' => $payment->method,
                'status' => PaymentTxStatus::Reversed,
                'reverses_id' => $payment->id,
                'reason' => $reason,
                'received_by' => $author->id,
                'collected_by' => $payment->collected_by,
                'paid_at' => now(),
            ]);

            $this->reverseCash($author, $payment, $reason);

            $order = $payment->order()->first();

            if ($order instanceof Order) {
                $this->balance->refresh($order);
            }

            return $reversal;
        });
    }

    private function reverseUncollectedFieldCash(Payment $payment): void
    {
        if ($payment->collected_by === null) {
            return;
        }
        User::query()->whereKey($payment->collected_by)->lockForUpdate()->firstOrFail();
        $ledger = app(DriverCashLedger::class);
        $paidAt = $payment->paid_at ?? $payment->created_at;
        $ambiguous = $paidAt === null || CashCollection::query()
            ->where('driver_id', $payment->collected_by)->where('created_at', '>=', $paidAt)->exists();
        if ($payment->status !== PaymentTxStatus::Pending || $ambiguous
            || $payment->amount->greaterThan($ledger->balanceOf($payment->collected_by))) {
            throw ValidationException::withMessages(['payment' => __('sales::payment.field_cash_requires_refund')]);
        }
        $ledger->debit($payment->collected_by, $payment->amount);
    }

    /**
     * Naqd to'lov kassa yozuvini tug'digan edi — o'shani ham teskari
     * qilamiz. Yozuv topilmasa (eski ma'lumot yoki naqd bo'lmagan
     * to'lov) — jim o'tamiz, chunki teskari qiladigan narsa yo'q.
     */
    private function reverseCash(User $author, Payment $payment, string $reason): void
    {
        if (! $payment->entersCashRegister()) {
            return;
        }

        $movement = CashMovement::query()
            ->withoutGlobalScopes()
            ->where('source_type', Payment::class)
            ->where('source_id', $payment->id)
            ->whereNull('reverses_id')
            ->first();

        if (! $movement instanceof CashMovement) {
            return;
        }

        $this->cash->reverse($author, $movement, $reason);
    }
}
