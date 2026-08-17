<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Policies;

use App\Modules\Core\Models\User;
use App\Modules\Payroll\Models\BonusEntry;

/**
 * Mukofot yozuvi — PERMISSIONS.md §9.
 *
 * `payroll.bonus.view_own` hammada bor — xodim o'z mukofotini ko'rishi
 * kerak, lekin boshqalarnikini ko'rmaydi. Ro'yxat shu sabab kontrollerda
 * `user_id` bo'yicha cheklanadi (`view_any` yo'q bo'lsa).
 */
final class BonusEntryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('payroll.bonus.view_own') || $user->can('payroll.bonus.view_any');
    }

    public function view(User $user, BonusEntry $entry): bool
    {
        if ($user->can('payroll.bonus.view_any')) {
            return true;
        }

        return $user->can('payroll.bonus.view_own') && $entry->user_id === $user->id;
    }

    public function approve(User $user): bool
    {
        return $user->can('payroll.bonus.approve');
    }

    public function pay(User $user): bool
    {
        return $user->can('payroll.bonus.pay');
    }
}
