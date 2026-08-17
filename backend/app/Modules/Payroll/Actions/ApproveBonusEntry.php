<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Actions;

use App\Modules\Core\Models\User;
use App\Modules\Payroll\Enums\BonusStatus;
use App\Modules\Payroll\Models\BonusEntry;
use Illuminate\Validation\ValidationException;

/**
 * Mukofot yozuvini tasdiqlash — PERMISSIONS.md §9.
 */
final class ApproveBonusEntry
{
    public function handle(User $approver, BonusEntry $entry): BonusEntry
    {
        if ($entry->status !== BonusStatus::Accrued) {
            throw ValidationException::withMessages([
                'entry' => __('payroll::bonus.not_accrued'),
            ]);
        }

        $entry->update(['status' => BonusStatus::Approved->value]);

        return $entry;
    }
}
