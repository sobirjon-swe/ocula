<?php

declare(strict_types=1);

namespace App\Modules\Sales\Policies;

use App\Modules\Core\Models\User;
use App\Support\Authorization\ResourcePolicy;
use Illuminate\Database\Eloquent\Model;

/**
 * Mijoz — PERMISSIONS.md §4.
 *
 * Filial cheklovi **yo'q**: mijoz butun tarmoqniki (SCHEMA.md §4).
 * `withinBranch()` shu sababli chaqirilmaydi — u `customers.branch_id`
 * ni ko'rib, boshqa filialda ro'yxatdan o'tgan mijozni yashirib
 * qo'yardi va o'sha odam ikkinchi marta kartochka ochishga majbur
 * bo'lardi.
 *
 * Mijoz o'chirilmaydi — tarixi bilan qoladi (yumshoq o'chirish).
 */
final class CustomerPolicy extends ResourcePolicy
{
    protected function permission(): string
    {
        return 'sales.customer';
    }

    public function view(User $user, Model $model): bool
    {
        return $user->can('sales.customer.view');
    }

    public function update(User $user, Model $model): bool
    {
        return $user->can('sales.customer.update');
    }

    public function delete(User $user, Model $model): bool
    {
        return false;
    }

    /**
     * Qarz ma'lumotini ko'rish alohida ruxsat (7.6).
     */
    public function viewDebt(User $user, Model $model): bool
    {
        return $user->can('sales.customer.debt.view');
    }
}
