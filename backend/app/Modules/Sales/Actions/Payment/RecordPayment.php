<?php

declare(strict_types=1);

namespace App\Modules\Sales\Actions\Payment;

use App\Modules\Core\Models\User;
use App\Modules\Finance\Enums\CashCategory;
use App\Modules\Finance\Services\CashRegister;
use App\Modules\Sales\Enums\OrderStatus;
use App\Modules\Sales\Enums\PaymentMethod;
use App\Modules\Sales\Enums\PaymentStatus;
use App\Modules\Sales\Enums\PaymentTxStatus;
use App\Modules\Sales\Models\Order;
use App\Modules\Sales\Models\Payment;
use App\Modules\Sales\Services\OrderBalance;
use App\Support\Money\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * To'lov qabul qilish — PROJECT.md 7.8-A, 7.21, ENUMS.md §4.
 *
 * Ikkita hisobot bir-biriga qo'shilib ketmasligi kerak (7.8): bu amal
 * **pul harakati**, tovar harakati emas. Buyurtma hali topshirilmagan
 * bo'lsa ham avans olinadi va u kassaga bugun tushadi.
 *
 * **Faqat naqd** kassaga tushadi. Karta terminal hisobiga, o'tkazma
 * bankka boradi — ularni kassa daftariga yozsak, smena yopilishida
 * seyfda yo'q pul "kutilgan" bo'lib chiqib, har kuni kamomad
 * ko'rinardi.
 *
 * Yozuv insert-only: muvaffaqiyatsiz to'lov umuman yozilmaydi.
 *
 * `$collectedInField` — haydovchi yetkazishda naqd yig'ganda (7.4,
 * BOSQICH-8.md §3). Bu holda to'lov `pending` bo'lib yoziladi va
 * **kassaga darrov tushmaydi** — pul hali haydovchi qo'lida, faqat
 * inkassatsiyada haqiqiy kassaga kiradi (`Delivery\Actions\Collection\CollectDriverCash`).
 * Mijozning qarzi esa shu zahoti kamayadi: `paidAmount()` to'lov
 * holatiga qaramay yig'indini oladi.
 */
final class RecordPayment
{
    public function __construct(
        private readonly CashRegister $cash,
        private readonly OrderBalance $balance,
    ) {}

    public function handle(
        User $author,
        Order $order,
        Money $amount,
        PaymentMethod $method,
        bool $collectedInField = false,
    ): Payment {
        if (! $amount->isPositive()) {
            throw ValidationException::withMessages([
                'amount' => __('sales::payment.amount_must_be_positive'),
            ]);
        }

        if ($order->status === OrderStatus::Cancelled) {
            throw ValidationException::withMessages([
                'order' => __('sales::payment.order_cancelled'),
            ]);
        }

        $remaining = $order->total->minus($order->paidAmount());

        if ($amount->greaterThan($remaining)) {
            throw ValidationException::withMessages([
                'amount' => __('sales::payment.exceeds_remaining', [
                    'remaining' => $remaining->format(),
                ]),
            ]);
        }

        return DB::transaction(function () use ($author, $order, $amount, $method, $collectedInField): Payment {
            $payment = Payment::create([
                'order_id' => $order->id,
                'customer_id' => $order->customer_id,
                'branch_id' => $order->branch_id,
                'shift_id' => $order->shift_id,
                'amount' => $amount->toString(),
                'method' => $method,
                'status' => $collectedInField ? PaymentTxStatus::Pending : PaymentTxStatus::Completed,
                'received_by' => $author->id,
                'collected_by' => $collectedInField ? $author->id : null,
                'paid_at' => now(),
            ]);

            // Dala sharoitida yig'ilgan pul hali kassada emas — u
            // faqat inkassatsiyada haqiqiy kassa yozuvini oladi
            // (BOSQICH-8.md §3).
            if (! $collectedInField && $method->entersCashRegister()) {
                $this->cash->record(
                    $author,
                    $order->branch_id,
                    $this->categoryFor($order),
                    $amount,
                    source: $payment,
                );
            }

            $this->balance->refresh($order);

            return $payment;
        });
    }

    /**
     * Kassa daftaridagi qator: odatdagi savdo tushumi yoki eski qarzning
     * to'lovi (ENUMS.md §8).
     *
     * Farq hisobot uchun muhim: qarz to'lovi bugungi **savdo** emas,
     * u kechagi savdoning puli.
     */
    private function categoryFor(Order $order): CashCategory
    {
        return $order->payment_status === PaymentStatus::Debt
            ? CashCategory::DebtPayment
            : CashCategory::Sale;
    }
}
