<?php

declare(strict_types=1);

namespace App\Support\Scopes;

use App\Support\Contracts\HasBranchAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

/**
 * Har bir so'rovni foydalanuvchi filialiga cheklaydi — PROJECT.md §4.
 *
 * Qoidalar:
 * - autentifikatsiya yo'q (konsol, queue, seeder, test) → cheklov **yo'q**;
 * - `director` / `accountant` → barcha filiallar (`canAccessAllBranches()`);
 * - qolganlar → asosiy filial + `branch_user` pivotdagi filiallar.
 *
 * Bitta ham filiali bo'lmagan xodim hech nima ko'rmaydi — bo'sh `whereIn`
 * emas, ataylab `whereRaw('1 = 0')`, chunki Postgresda bo'sh `IN ()` xato.
 *
 * Chetlab o'tish: `Model::withoutGlobalScope(BranchScope::class)` — faqat
 * hisobot va fon jarayonlarida, sababi izohda ko'rsatilgan holda.
 *
 * @implements Scope<Model>
 */
final class BranchScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $user = Auth::user();

        if (! $user instanceof HasBranchAccess) {
            return;
        }

        if ($user->canAccessAllBranches()) {
            return;
        }

        $branchIds = $user->accessibleBranchIds();

        if ($branchIds === []) {
            $builder->whereRaw('1 = 0');

            return;
        }

        $builder->whereIn($model->qualifyColumn('branch_id'), $branchIds);
    }
}
