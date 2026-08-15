<?php

declare(strict_types=1);

namespace App\Modules\Warehouse\Policies;

use App\Modules\Core\Models\User;
use App\Support\Authorization\ResourcePolicy;
use Illuminate\Database\Eloquent\Model;

/**
 * Transfer — PERMISSIONS.md §3.
 *
 * Tayyorlash, jo'natish, qabul qilish va farqni hal qilish — **to'rtta
 * alohida ruxsat**. Bu ataylab: jo'natgan odam o'zi qabul qilib,
 * yo'ldagi kamomadni o'zi yopib qo'ymasligi kerak. Sotuvchida faqat
 * `receive` bor — u kelgan tovarni sanaydi.
 *
 * Filial cheklovi bu yerda `withinBranch()` orqali emas: transferda
 * `branch_id` ustuni yo'q, ikkala tomoni ham uni ko'radi. Cheklovni
 * `TwoSidedBranchScope` qiladi — begona transfer route model binding
 * bosqichidayoq 404 bo'ladi.
 *
 * Hujjat o'chirilmaydi: bekor qilinadi yoki oxirigacha boradi.
 */
final class TransferPolicy extends ResourcePolicy
{
    protected function permission(): string
    {
        return 'warehouse.transfer';
    }

    /**
     * Qabul qilish huquqi ko'rish huquqini ham beradi.
     *
     * Sotuvchida `transfer.view_any` yo'q, lekin `transfer.receive` bor
     * (PERMISSIONS.md §3 matritsasi). Kelgan tovarni sanash uchun u
     * hujjatni ocha olishi kerak — aks holda unga berilgan ruxsatni
     * amalda ishlatib bo'lmasdi.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('warehouse.transfer.view_any')
            || $user->can('warehouse.transfer.receive');
    }

    public function view(User $user, Model $model): bool
    {
        return $this->viewAny($user);
    }

    public function send(User $user, Model $model): bool
    {
        return $user->can('warehouse.transfer.send');
    }

    public function receive(User $user, Model $model): bool
    {
        return $user->can('warehouse.transfer.receive');
    }

    public function cancel(User $user, Model $model): bool
    {
        return $user->can('warehouse.transfer.cancel');
    }

    public function resolveDiscrepancy(User $user, Model $model): bool
    {
        return $user->can('warehouse.transfer.resolve_discrepancy');
    }

    public function update(User $user, Model $model): bool
    {
        return $user->can('warehouse.transfer.create');
    }

    public function delete(User $user, Model $model): bool
    {
        return false;
    }
}
