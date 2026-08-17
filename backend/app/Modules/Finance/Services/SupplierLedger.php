<?php

declare(strict_types=1);

namespace App\Modules\Finance\Services;

use App\Modules\Core\Models\User;
use App\Modules\Finance\Enums\CashCategory;
use App\Modules\Finance\Enums\SupplierTxType;
use App\Modules\Finance\Models\SupplierTransaction;
use App\Modules\Warehouse\Models\Purchase;
use App\Modules\Warehouse\Models\Supplier;
use App\Support\Money\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Yetkazib beruvchi bilan ikki tomonlama hisob — PROJECT.md 7.15,
 * BOSQICH-10.md §10a.
 *
 * `CashRegister`/`StockLedger` bilan bir xil naqsh: bu klass
 * **`supplier_transactions` ga yozadigan yagona joy**, balans yozuvlar
 * yig'indisidan hisoblanadi.
 *
 * `amount` har doim musbat kiritiladi, ishorani `type` beradi:
 * `purchase` — manfiy (qarzimiz oshadi), `payment`/`correction` —
 * musbat (qarzimiz kamayadi). Faqat `payment` kassadan chiqim ham
 * yozadi — `purchase` paytida naqd harakatlanmaydi, faqat qarz ochiladi.
 */
final class SupplierLedger
{
    public function __construct(private readonly CashRegister $cash) {}

    /**
     * `ReceivePurchase` (Warehouse) chaqiradi — "tovar keldi, to'lanmadi".
     */
    public function recordPurchase(User $author, Supplier $supplier, Purchase $purchase): SupplierTransaction
    {
        $dueDate = CarbonImmutable::now()->addDays($supplier->payment_terms_days);

        return $this->write($author, $supplier, SupplierTxType::Purchase, $purchase->total, $purchase, $dueDate);
    }

    /**
     * Qo'lda to'lov — `POST /supplier-transactions`. Kassadan avtomatik
     * chiqim yozadi (`CashCategory::SupplierPayment`).
     */
    public function recordPayment(
        User $author,
        Supplier $supplier,
        int $branchId,
        Money $amount,
        ?Purchase $purchase = null,
        ?string $reason = null,
    ): SupplierTransaction {
        $transaction = $this->write($author, $supplier, SupplierTxType::Payment, $amount, $purchase, null, $reason);

        $this->cash->record(
            $author,
            $branchId,
            CashCategory::SupplierPayment,
            $amount,
            source: $transaction,
            description: $reason,
        );

        return $transaction;
    }

    /**
     * Qo'lda tuzatish — kassaga tegmaydi, faqat hisobni to'g'rilaydi.
     */
    public function recordCorrection(User $author, Supplier $supplier, Money $signedAmount, string $reason): SupplierTransaction
    {
        if ($signedAmount->isZero()) {
            throw ValidationException::withMessages([
                'amount' => __('finance::supplier.correction_amount_required'),
            ]);
        }

        return SupplierTransaction::create([
            'supplier_id' => $supplier->id,
            'type' => SupplierTxType::Correction,
            'amount' => $signedAmount->toString(),
            'reason' => $reason,
            'created_by' => $author->id,
        ]);
    }

    /**
     * Joriy balans — musbat: yetkazib beruvchi qarzdor (avans), manfiy:
     * biz qarzdormiz (7.15 jadvaliga mos).
     */
    public function balance(Supplier $supplier): Money
    {
        $sum = SupplierTransaction::query()->where('supplier_id', $supplier->id)->sum('amount');

        return Money::of((string) $sum);
    }

    private function write(
        User $author,
        Supplier $supplier,
        SupplierTxType $type,
        Money $amount,
        ?Purchase $purchase,
        ?CarbonImmutable $dueDate,
        ?string $reason = null,
    ): SupplierTransaction {
        if (! $amount->isPositive()) {
            throw ValidationException::withMessages([
                'amount' => __('finance::supplier.amount_must_be_positive'),
            ]);
        }

        $signed = $type === SupplierTxType::Purchase ? $amount->negated() : $amount;

        return DB::transaction(fn (): SupplierTransaction => SupplierTransaction::create([
            'supplier_id' => $supplier->id,
            'type' => $type,
            'amount' => $signed->toString(),
            'purchase_id' => $purchase?->id,
            'due_date' => $dueDate?->toDateString(),
            'reason' => $reason,
            'created_by' => $author->id,
        ]));
    }
}
