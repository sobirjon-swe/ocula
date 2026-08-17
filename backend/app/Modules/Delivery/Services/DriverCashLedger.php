<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Services;

use App\Modules\Delivery\Models\DriverBalance;
use App\Support\Money\Money;
use Illuminate\Support\Facades\DB;

/**
 * Haydovchi qo'lidagi pul keshi — PROJECT.md §5.2, BOSQICH-8.md §3.
 *
 * `StockLedger::applyToBalance()` bilan bir xil mantiq: `ON CONFLICT
 * ... DO UPDATE` — bitta atomar so'rov, parallel yetkazish/inkassatsiyada
 * qiymat yo'qolmasin.
 *
 * Haqiqat manbai bu kesh emas: `payments` (mijozdan yig'ilgan, `pending`)
 * va `collections` (topshirilgan). Kesh faqat tezlik uchun.
 */
final class DriverCashLedger
{
    public function balanceOf(int $driverId): Money
    {
        $row = DriverBalance::query()->find($driverId);

        return $row instanceof DriverBalance ? $row->cash_amount : Money::zero();
    }

    /**
     * Mijozdan naqd yig'ilganda balans oshadi.
     */
    public function credit(int $driverId, Money $amount): void
    {
        $this->applyDelta($driverId, $amount);
    }

    /**
     * Kassaga topshirilganda balans kamayadi.
     */
    public function debit(int $driverId, Money $amount): void
    {
        $this->applyDelta($driverId, $amount->negated());
    }

    private function applyDelta(int $driverId, Money $delta): void
    {
        DB::statement(
            'INSERT INTO driver_balances (driver_id, cash_amount, updated_at)
             VALUES (?, ?, ?)
             ON CONFLICT (driver_id)
             DO UPDATE SET cash_amount = driver_balances.cash_amount + EXCLUDED.cash_amount,
                           updated_at = EXCLUDED.updated_at',
            [$driverId, $delta->toString(), now()],
        );
    }
}
