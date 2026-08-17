<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Services;

use App\Modules\Core\Models\User;
use App\Modules\Payroll\Models\BonusRule;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Mukofot qoidasini topish — PROJECT.md 7.19, BOSQICH-10.md §10b.
 *
 * Ustunlik tartibi: **shaxsiy qoida** (`user_id`) → filialga mos rol
 * qoidasi → tarmoq bo'yicha umumiy rol qoidasi. Har bir bosqichda faqat
 * berilgan sanada joriy bo'lgan (`valid_from..valid_to`) qoida olinadi.
 */
final class BonusRuleResolver
{
    public function resolve(User $user, ?CarbonImmutable $date = null): ?BonusRule
    {
        $date ??= CarbonImmutable::today();

        $personal = BonusRule::query()
            ->where('user_id', $user->id)
            ->activeOn($date)
            ->orderByDesc('valid_from')
            ->first();

        if ($personal instanceof BonusRule) {
            return $personal;
        }

        $roles = $user->getRoleNames();

        if ($roles->isEmpty()) {
            return null;
        }

        return BonusRule::query()
            ->whereNull('user_id')
            ->whereIn('role', $roles)
            ->where(function (Builder $query) use ($user): void {
                $query->where('branch_id', $user->branch_id)->orWhereNull('branch_id');
            })
            ->activeOn($date)
            // Filialga mos qoida (branch_id IS NOT NULL) tarmoq bo'yicha
            // umumiysidan ustun — Postgres'da false (0) true (1) dan oldin.
            ->orderByRaw('branch_id IS NULL')
            ->orderByDesc('valid_from')
            ->first();
    }
}
