<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Actions\Collection;

use App\Modules\Core\Models\User;
use App\Modules\Delivery\Models\CashCollection;
use App\Modules\Delivery\Services\DriverCashLedger;
use App\Modules\Finance\Enums\CashCategory;
use App\Modules\Finance\Services\CashRegister;
use App\Support\Money\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Inkassatsiya — haydovchidan kassaga pul topshirish.
 * PROJECT.md §5.2, 7.4, BOSQICH-8.md §3.
 *
 * Bu — pul haqiqatan **kassaga tushadigan** yagona joy dala sharoitida
 * yig'ilgan naqd uchun. Undan oldin (`RecordPayment(..., collectedInField: true)`)
 * pul faqat haydovchi balansida edi.
 */
final class CollectDriverCash
{
    public function __construct(
        private readonly DriverCashLedger $ledger,
        private readonly CashRegister $cash,
    ) {}

    public function handle(
        User $receiver,
        User $driver,
        int $branchId,
        Money $amount,
        ?int $shiftId = null,
        ?string $note = null,
    ): CashCollection {
        if (! $amount->isPositive()) {
            throw ValidationException::withMessages([
                'amount' => __('delivery::collection.amount_must_be_positive'),
            ]);
        }

        $balance = $this->ledger->balanceOf($driver->id);

        if ($amount->greaterThan($balance)) {
            throw ValidationException::withMessages([
                'amount' => __('delivery::collection.exceeds_balance', [
                    'balance' => $balance->format(),
                ]),
            ]);
        }

        return DB::transaction(function () use ($receiver, $driver, $branchId, $amount, $shiftId, $note): CashCollection {
            $collection = CashCollection::create([
                'driver_id' => $driver->id,
                'branch_id' => $branchId,
                'shift_id' => $shiftId,
                'amount' => $amount->toString(),
                'received_by' => $receiver->id,
                'note' => $note,
            ]);

            $this->ledger->debit($driver->id, $amount);

            $this->cash->record(
                $receiver,
                $branchId,
                CashCategory::Collection,
                $amount,
                source: $collection,
            );

            return $collection;
        });
    }
}
