<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Actions;

use App\Modules\Core\Models\User;
use App\Modules\Finance\Enums\CashCategory;
use App\Modules\Finance\Services\CashRegister;
use App\Modules\Payroll\Enums\BonusStatus;
use App\Modules\Payroll\Models\BonusEntry;
use Illuminate\Validation\ValidationException;

/**
 * Mukofotni to'lash — PERMISSIONS.md §9.
 *
 * Kassadan avtomatik chiqim yoziladi (`CashCategory::BonusPayout`,
 * ENUMS.md §8) — pul va daftar bir amalda ajralmaydi.
 */
final class PayBonusEntry
{
    public function __construct(private readonly CashRegister $cash) {}

    public function handle(User $payer, BonusEntry $entry, int $branchId): BonusEntry
    {
        if (! in_array($entry->status, [BonusStatus::Accrued, BonusStatus::Approved], true)) {
            throw ValidationException::withMessages([
                'entry' => __('payroll::bonus.not_payable'),
            ]);
        }

        $entry->update(['status' => BonusStatus::Paid->value]);

        $this->cash->record(
            $payer,
            $branchId,
            CashCategory::BonusPayout,
            $entry->amount,
            source: $entry,
        );

        return $entry;
    }
}
