<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Actions;

use App\Modules\Core\Models\User;
use App\Modules\Finance\Enums\CashCategory;
use App\Modules\Finance\Services\CashRegister;
use App\Modules\Payroll\Enums\BonusStatus;
use App\Modules\Payroll\Models\BonusEntry;
use Illuminate\Support\Facades\DB;
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
        return DB::transaction(function () use ($payer, $entry, $branchId): BonusEntry {
            $entry = BonusEntry::query()->lockForUpdate()->findOrFail($entry->id);
            if (! in_array($entry->status, [BonusStatus::Accrued, BonusStatus::Approved], true)) {
                throw ValidationException::withMessages([
                    'entry' => __('payroll::bonus.not_payable'),
                ]);
            }

            $net = $entry->amount->plus((string) BonusEntry::where('reverses_id', $entry->id)->sum('amount'));
            if (! $net->isPositive()) {
                throw ValidationException::withMessages(['entry' => __('payroll::bonus.not_payable')]);
            }

            $entry->update(['status' => BonusStatus::Paid->value]);

            $this->cash->record(
                $payer,
                $branchId,
                CashCategory::BonusPayout,
                $net,
                source: $entry,
            );

            return $entry;
        });
    }
}
